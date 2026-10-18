<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../notifications-helper.php';

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador', 'Trabajador']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $connection->query(
        "SELECT p.id, p.numero, p.total, p.estado, p.estado_pago, p.creado_en,
                p.metodo_entrega, p.direccion_entrega, p.notas,
                TRIM(CONCAT(u.nombre, ' ', COALESCE(u.apellido, ''))) AS cliente
         FROM pedidos p INNER JOIN usuarios u ON u.id = p.usuario_id
         ORDER BY p.creado_en DESC LIMIT 100"
    );
    if (!$result) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar los pedidos.']);
    }
    $orders = [];
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['id'];
        $ids[] = $id;
        $orders[$id] = [
            'id' => $id, 'number' => (string) $row['numero'], 'customer' => (string) $row['cliente'],
            'total' => (float) $row['total'], 'status' => (string) $row['estado'],
            'paymentStatus' => (string) $row['estado_pago'], 'createdAt' => (string) $row['creado_en'],
            'fulfillmentMethod' => (string) $row['metodo_entrega'],
            'fulfillmentDetails' => (string) ($row['direccion_entrega'] ?? ''),
            'notes' => (string) ($row['notas'] ?? ''), 'items' => [], 'history' => [],
        ];
    }

    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $items = $connection->prepare(
            "SELECT d.pedido_id, d.cantidad, d.precio_unitario, COALESCE(d.nombre_producto, p.nombre) AS nombre
             FROM detalle_pedido d INNER JOIN productos p ON p.id = d.producto_id
             WHERE d.pedido_id IN ($marks) ORDER BY d.pedido_id, d.id"
        );
        $items->bind_param($types, ...$ids);
        $items->execute();
        $itemRows = $items->get_result();
        while ($item = $itemRows->fetch_assoc()) {
            $orders[(int) $item['pedido_id']]['items'][] = [
                'name' => (string) $item['nombre'], 'quantity' => (int) $item['cantidad'],
                'price' => (float) $item['precio_unitario'],
            ];
        }
        $items->close();

        $history = $connection->prepare(
            "SELECT h.pedido_id, h.estado_anterior, h.estado_nuevo, h.metodo_entrega_nuevo,
                    h.detalle_entrega, h.creado_en, TRIM(CONCAT(u.nombre, ' ', COALESCE(u.apellido, ''))) AS actor
             FROM historial_pedido h LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.pedido_id IN ($marks) ORDER BY h.creado_en DESC, h.id DESC"
        );
        $history->bind_param($types, ...$ids);
        $history->execute();
        $historyRows = $history->get_result();
        while ($entry = $historyRows->fetch_assoc()) {
            $orders[(int) $entry['pedido_id']]['history'][] = [
                'previousStatus' => (string) ($entry['estado_anterior'] ?? ''),
                'status' => (string) $entry['estado_nuevo'],
                'fulfillmentMethod' => (string) $entry['metodo_entrega_nuevo'],
                'fulfillmentDetails' => (string) ($entry['detalle_entrega'] ?? ''),
                'createdAt' => (string) $entry['creado_en'], 'actor' => (string) ($entry['actor'] ?? 'Sistema'),
            ];
        }
        $history->close();
    }
    $connection->close();
    jsonResponse(200, ['orders' => array_values($orders)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $connection->close();
    jsonResponse(405, ['message' => 'Método no permitido.']);
}
requireCsrfToken();
$body = json_decode(file_get_contents('php://input'), true);
$orderId = filter_var($body['orderId'] ?? null, FILTER_VALIDATE_INT);
$nextStatus = strtoupper(trim((string) ($body['status'] ?? '')));
$nextMethod = strtoupper(trim((string) ($body['fulfillmentMethod'] ?? 'POR_COORDINAR')));
$details = trim((string) ($body['fulfillmentDetails'] ?? ''));
$allowedMethods = ['POR_COORDINAR', 'DOMICILIO', 'RECOGIDA'];
$allowedStatuses = ['PENDIENTE', 'CONFIRMADO', 'PREPARANDO', 'LISTO_PARA_COORDINAR', 'COMPLETADO', 'ENVIADO', 'ENTREGADO', 'CANCELADO'];
if (!$orderId || !in_array($nextStatus, $allowedStatuses, true) || !in_array($nextMethod, $allowedMethods, true) || mb_strlen($details) > 255) {
    $connection->close();
    jsonResponse(422, ['message' => 'Los datos del pedido no son válidos.']);
}
if ($nextMethod !== 'POR_COORDINAR' && $details === '') {
    $connection->close();
    jsonResponse(422, ['message' => 'Anota el punto o la dirección acordada para esta forma de entrega.']);
}

try {
    $connection->begin_transaction();
    $select = $connection->prepare('SELECT numero, usuario_id, estado, estado_pago, metodo_entrega, direccion_entrega FROM pedidos WHERE id = ? FOR UPDATE');
    $select->bind_param('i', $orderId);
    $select->execute();
    $current = $select->get_result()->fetch_assoc();
    $select->close();
    if (!$current) throw new DomainException('El pedido ya no existe.');
    $paymentCaptured = in_array($current['estado_pago'], ['APROBADO', 'CAPTURADO'], true);
    if (!$paymentCaptured) {
        if (!($nextStatus === 'CANCELADO' && $nextMethod === 'POR_COORDINAR' && $current['estado'] === 'PENDIENTE')) {
            throw new DomainException('Solo se pueden preparar pedidos con pago aprobado.');
        }
    }
    $transitions = [
        'CONFIRMADO' => ['PREPARANDO'],
        'PREPARANDO' => ['LISTO_PARA_COORDINAR'],
        'LISTO_PARA_COORDINAR' => ['COMPLETADO'],
        'ENVIADO' => ['ENTREGADO'],
    ];
    $previousStatus = (string) $current['estado'];
    $previousMethod = (string) $current['metodo_entrega'];
    $previousDetails = (string) ($current['direccion_entrega'] ?? '');
    $statusChanged = $nextStatus !== $previousStatus;
    $methodChanged = $nextMethod !== $previousMethod || $details !== $previousDetails;
    if ($statusChanged && !in_array($nextStatus, $transitions[$previousStatus] ?? [], true) && $nextStatus !== 'CANCELADO') {
        throw new DomainException('Ese cambio de estado no está permitido para el pedido.');
    }
    if ($nextStatus === 'CANCELADO' && $paymentCaptured) {
        throw new DomainException('Un pedido pagado no se puede cancelar desde esta vista. Gestiona primero el reembolso y el inventario.');
    }
    if ($nextStatus === 'COMPLETADO' && $nextMethod === 'POR_COORDINAR') {
        throw new DomainException('Acuerda primero si será domicilio o recogida y registra el punto correspondiente.');
    }
    if (!$statusChanged && !$methodChanged) throw new DomainException('No hay cambios para guardar.');

    $update = $connection->prepare('UPDATE pedidos SET estado = ?, metodo_entrega = ?, direccion_entrega = NULLIF(?, \'\') WHERE id = ?');
    $update->bind_param('sssi', $nextStatus, $nextMethod, $details, $orderId);
    if (!$update->execute()) throw new RuntimeException('No fue posible actualizar el pedido.');
    $update->close();
    $history = $connection->prepare(
        'INSERT INTO historial_pedido (pedido_id, usuario_id, estado_anterior, estado_nuevo,
         metodo_entrega_anterior, metodo_entrega_nuevo, detalle_entrega)
         VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, \'\'))'
    );
    $actorId = (int) $actor['id'];
    $history->bind_param('iisssss', $orderId, $actorId, $previousStatus, $nextStatus, $previousMethod, $nextMethod, $details);
    if (!$history->execute()) throw new RuntimeException('No fue posible guardar el historial del pedido.');
    $history->close();

    // Notificar al cliente propietario del pedido
    if ($statusChanged || $methodChanged) {
        $customerUserId = (int) $current['usuario_id'];
        $orderNumber = (string) $current['numero'];
        $statusDescriptions = [
            'CONFIRMADO' => ['title' => 'Pedido confirmado', 'msg' => "Tu pedido #$orderNumber ha sido confirmado."],
            'PREPARANDO' => ['title' => 'Pedido en preparación', 'msg' => "Tu pedido #$orderNumber está siendo preparado para el envío."],
            'LISTO_PARA_COORDINAR' => ['title' => 'Pedido listo para entrega o recogida', 'msg' => "Tu pedido #$orderNumber está listo. " . ($details !== '' ? "Detalles: $details." : "")],
            'ENVIADO' => ['title' => 'Pedido en camino', 'msg' => "Tu pedido #$orderNumber ha sido despachado a tu dirección."],
            'ENTREGADO' => ['title' => 'Pedido entregado', 'msg' => "Tu pedido #$orderNumber ha sido entregado exitosamente. ¡Que lo disfrutes!"],
            'COMPLETADO' => ['title' => 'Pedido completado', 'msg' => "Tu pedido #$orderNumber se ha completado con éxito."],
            'CANCELADO' => ['title' => 'Pedido cancelado', 'msg' => "Tu pedido #$orderNumber ha sido cancelado."],
        ];
        $info = $statusDescriptions[$nextStatus] ?? [
            'title' => 'Actualización de pedido',
            'msg' => "Tu pedido #$orderNumber ha cambiado a estado $nextStatus.",
        ];
        createNotification($connection, $customerUserId, 'ESTADO_PEDIDO', $info['title'], $info['msg'], $orderId);
    }

    $connection->commit();
    $connection->close();
    jsonResponse(200, ['message' => 'Estado del pedido actualizado.', 'order' => [
        'id' => (int) $orderId, 'status' => $nextStatus, 'fulfillmentMethod' => $nextMethod, 'fulfillmentDetails' => $details,
    ]]);
} catch (Throwable $error) {
    if ($connection->connect_errno === 0) $connection->rollback();
    $connection->close();
    if ($error instanceof DomainException) jsonResponse(422, ['message' => $error->getMessage()]);
    error_log('LiquorSoft order update failed: ' . $error->getMessage());
    jsonResponse(500, ['message' => 'No fue posible actualizar el pedido.']);
}
