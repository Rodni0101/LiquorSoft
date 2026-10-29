<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/paypal.php';
require_once __DIR__ . '/../notifications-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$configuration = paypalConfiguration();
if (($configuration['webhookId'] ?? '') === '') {
    error_log('LiquorSoft PayPal webhook rejected: PAYPAL_WEBHOOK_ID is not configured.');
    jsonResponse(503, ['message' => 'Webhook no configurado.']);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 1_000_000) {
    jsonResponse(400, ['message' => 'Evento inválido.']);
}
$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['id']) || empty($event['event_type'])) {
    jsonResponse(400, ['message' => 'Evento inválido.']);
}

$headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
$requiredHeaders = [
    'paypal-auth-algo',
    'paypal-cert-url',
    'paypal-transmission-id',
    'paypal-transmission-sig',
    'paypal-transmission-time',
];
foreach ($requiredHeaders as $header) {
    if (empty($headers[$header])) {
        jsonResponse(400, ['message' => 'Faltan cabeceras de seguridad de PayPal.']);
    }
}

try {
    $verification = paypalApiRequest('POST', '/v1/notifications/verify-webhook-signature', [
        'auth_algo' => (string) $headers['paypal-auth-algo'],
        'cert_url' => (string) $headers['paypal-cert-url'],
        'transmission_id' => (string) $headers['paypal-transmission-id'],
        'transmission_sig' => (string) $headers['paypal-transmission-sig'],
        'transmission_time' => (string) $headers['paypal-transmission-time'],
        'webhook_id' => (string) $configuration['webhookId'],
        'webhook_event' => $event,
    ]);
} catch (Throwable $error) {
    error_log('LiquorSoft PayPal webhook verification failed: ' . $error->getMessage());
    jsonResponse(503, ['message' => 'No fue posible verificar el webhook.']);
}

if (($verification['verification_status'] ?? '') !== 'SUCCESS') {
    error_log('LiquorSoft PayPal webhook signature rejected for event ' . (string) $event['id'] . '.');
    jsonResponse(400, ['message' => 'Firma de webhook inválida.']);
}

$resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
$customId = trim((string) ($resource['custom_id'] ?? ''));
if ($customId === '') {
    $units = $resource['purchase_units'] ?? [];
    if (is_array($units) && isset($units[0]) && is_array($units[0])) {
        $customId = trim((string) ($units[0]['custom_id'] ?? ''));
    }
}
$relatedIds = $resource['supplementary_data']['related_ids'] ?? [];
$paypalOrderId = is_array($relatedIds) ? trim((string) ($relatedIds['order_id'] ?? '')) : '';
$resourceId = trim((string) ($resource['id'] ?? ''));
$eventId = substr((string) $event['id'], 0, 64);
$eventType = substr((string) $event['event_type'], 0, 80);

$connection = databaseConnection();
try {
    $payload = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $connection->begin_transaction();

    // El INSERT IGNORE dentro de la transacción serializa entregas concurrentes
    // del mismo evento: la segunda espera al commit de la primera.
    $insert = $connection->prepare(
        'INSERT IGNORE INTO paypal_webhook_events
         (event_id, event_type, resource_id, paypal_order_id, payload)
         VALUES (?, ?, ?, ?, ?)'
    );
    $resourceIdDb = $resourceId !== '' ? $resourceId : null;
    $paypalOrderIdDb = $paypalOrderId !== '' ? $paypalOrderId : null;
    $insert->bind_param('sssss', $eventId, $eventType, $resourceIdDb, $paypalOrderIdDb, $payload);
    if (!$insert->execute()) {
        throw new RuntimeException('No fue posible registrar el evento.');
    }
    $isNewEvent = $insert->affected_rows === 1;
    $insert->close();
    if (!$isNewEvent) {
        $existingEvent = $connection->prepare(
            'SELECT processed FROM paypal_webhook_events WHERE event_id = ? LIMIT 1'
        );
        $existingEvent->bind_param('s', $eventId);
        $existingEvent->execute();
        $existingRow = $existingEvent->get_result()->fetch_assoc();
        $existingEvent->close();
        if (!$existingRow) {
            throw new RuntimeException('No fue posible registrar el evento.');
        }
        if ((int) $existingRow['processed'] === 1) {
            $connection->commit();
            $connection->close();
            jsonResponse(200, ['received' => true, 'duplicate' => true]);
        }
        // Un intento anterior falló antes de marcar el evento; PayPal lo reintenta.
        $retry = $connection->prepare(
            'UPDATE paypal_webhook_events SET error = NULL, procesado_en = NULL WHERE event_id = ?'
        );
        $retry->bind_param('s', $eventId);
        $retry->execute();
        $retry->close();
    }

    $intent = null;
    if ($customId !== '' || $paypalOrderId !== '') {
        $intentQuery = $connection->prepare(
            'SELECT i.clave_idempotencia, i.paypal_order_id, i.usuario_id, i.estado, i.capture_id,
                    p.id AS pedido_id, p.numero, p.estado_pago, p.estado, p.metodo_pago, p.total
             FROM paypal_checkout_intents i
             LEFT JOIN pedidos p
               ON p.clave_idempotencia = i.clave_idempotencia AND p.usuario_id = i.usuario_id
             WHERE i.clave_idempotencia = ? OR i.paypal_order_id = ?
             LIMIT 1'
        );
        $intentQuery->bind_param('ss', $customId, $paypalOrderId);
        $intentQuery->execute();
        $intent = $intentQuery->get_result()->fetch_assoc();
        $intentQuery->close();
    }

    $order = null;
    if (!$intent && $customId !== '') {
        $orderQuery = $connection->prepare(
            'SELECT id AS pedido_id, numero, estado_pago, estado, metodo_pago, total
             FROM pedidos WHERE clave_idempotencia = ? LIMIT 1'
        );
        $orderQuery->bind_param('s', $customId);
        $orderQuery->execute();
        $order = $orderQuery->get_result()->fetch_assoc();
        $orderQuery->close();
    } elseif ($intent && !empty($intent['pedido_id'])) {
        $order = $intent;
    }

    $orderId = $order ? (int) $order['pedido_id'] : null;
    $message = '';
    if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
        if ($orderId) {
            $message = "Pago confirmado por PayPal para el pedido #{$order['numero']}. Captura: {$resourceId}.";
            if ((string) $order['estado_pago'] !== 'CAPTURADO') {
                notifyAdmins(
                    $connection,
                    'PAYPAL_PAGO_CONFIRMADO',
                    'Pago PayPal confirmado',
                    $message . ' El pedido no estaba marcado como capturado; revisa su estado.',
                    $orderId
                );
            }
        } else {
            $message = 'PayPal confirmó un cobro sin pedido local. Clave: ' . ($customId !== '' ? $customId : 'sin custom_id')
                . '. Orden: ' . ($paypalOrderId !== '' ? $paypalOrderId : 'desconocida')
                . '. Captura: ' . ($resourceId !== '' ? $resourceId : 'desconocida') . '.';
            notifyAdmins(
                $connection,
                'PAYPAL_RECONCILIACION',
                'Cobro PayPal sin pedido registrado',
                $message . ' Revisa PayPal y registra o reembolsa manualmente.',
                null
            );
        }
    } elseif ($eventType === 'PAYMENT.CAPTURE.DENIED') {
        if ($orderId) {
            $denied = $connection->prepare(
                "UPDATE pedidos SET estado_pago = 'RECHAZADO'
                 WHERE id = ? AND COALESCE(estado_pago, 'PENDIENTE') = 'PENDIENTE'"
            );
            $denied->bind_param('i', $orderId);
            $denied->execute();
            $denied->close();
            $history = $connection->prepare(
                "INSERT INTO historial_pedido (pedido_id, usuario_id, estado_anterior, estado_nuevo, metodo_entrega_nuevo, detalle_entrega)
                 VALUES (?, NULL, ?, 'PAGO_RECHAZADO', 'POR_COORDINAR', ?)"
            );
            $previousStatus = (string) $order['estado'];
            $detail = 'PayPal rechazó la captura ' . $resourceId;
            $history->bind_param('iss', $orderId, $previousStatus, $detail);
            $history->execute();
            $history->close();
        }
    } elseif ($eventType === 'PAYMENT.CAPTURE.REFUNDED') {
        if ($orderId) {
            $refunded = $connection->prepare("UPDATE pedidos SET estado_pago = 'REEMBOLSADO' WHERE id = ?");
            $refunded->bind_param('i', $orderId);
            $refunded->execute();
            $refunded->close();
            $history = $connection->prepare(
                "INSERT INTO historial_pedido (pedido_id, usuario_id, estado_anterior, estado_nuevo, metodo_entrega_nuevo, detalle_entrega)
                 VALUES (?, NULL, ?, 'PAGO_REEMBOLSADO', 'POR_COORDINAR', ?)"
            );
            $previousStatus = (string) $order['estado'];
            $detail = 'Reembolso PayPal ' . $resourceId;
            $history->bind_param('iss', $orderId, $previousStatus, $detail);
            $history->execute();
            $history->close();
        }
    }

    $processed = $connection->prepare(
        'UPDATE paypal_webhook_events SET processed = 1, procesado_en = CURRENT_TIMESTAMP, error = NULL WHERE event_id = ?'
    );
    $processed->bind_param('s', $eventId);
    $processed->execute();
    $processed->close();

    $connection->commit();
    $connection->close();
    jsonResponse(200, ['received' => true]);
} catch (Throwable $error) {
    error_log('LiquorSoft PayPal webhook processing failed: ' . $error->getMessage());
    if ($connection->connect_errno === 0) {
        try {
            $connection->rollback();
        } catch (Throwable) {
            // La transacción pudo haberse cerrado antes del error.
        }
        $errorMessage = substr($error->getMessage(), 0, 500);
        $failed = $connection->prepare(
            'UPDATE paypal_webhook_events SET error = ?, procesado_en = CURRENT_TIMESTAMP WHERE event_id = ?'
        );
        if ($failed) {
            $failed->bind_param('ss', $errorMessage, $eventId);
            $failed->execute();
            $failed->close();
        }
    }
    if ($connection->connect_errno === 0) {
        $connection->close();
    }
    jsonResponse(500, ['message' => 'No fue posible procesar el evento.']);
}
