<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paypal.php';
require_once __DIR__ . '/notifications-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}
requireCsrfToken();

$payload = json_decode(file_get_contents('php://input'), true);
$items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
$method = (string) ($payload['paymentMethod'] ?? '');
$outcome = (string) ($payload['simulationOutcome'] ?? '');
$paypalOrderId = trim((string) ($payload['paypalOrderId'] ?? ''));
$key = strtolower(trim((string) ($payload['idempotencyKey'] ?? '')));
$address = trim((string) ($payload['deliveryAddress'] ?? ''));
$birthDateInput = trim((string) ($payload['birthDate'] ?? ''));
$notes = trim((string) ($payload['notes'] ?? ''));

if ($method === 'nequi_simulado') {
    if (!paymentSimulatorEnabled()) {
        jsonResponse(503, ['message' => 'El simulador de pago no está habilitado en este entorno.']);
    }
    if (!in_array($outcome, ['approved', 'rejected', 'pending', 'cancelled', 'error'], true)) {
        jsonResponse(422, ['message' => 'Selecciona un resultado de prueba válido.']);
    }
} elseif ($method === 'paypal') {
    if (!paypalConfiguration()['enabled']) {
        jsonResponse(503, ['message' => 'PayPal no está habilitado. Revisa las credenciales y la tasa COP/USD del servidor.']);
    }
    if ($paypalOrderId === '' || strlen($paypalOrderId) > 64 || !preg_match('/^[A-Z0-9-]+$/', $paypalOrderId)) {
        jsonResponse(422, ['message' => 'La orden de PayPal no es válida.']);
    }
    $outcome = 'approved';
} else {
    jsonResponse(422, ['message' => 'Selecciona un método de pago habilitado.']);
}

if (!$items || count($items) > 40 || strlen($key) !== 36 || !preg_match('/^[0-9a-f-]{36}$/', $key)) {
    jsonResponse(422, ['message' => 'El carrito o la clave de compra no son válidos.']);
}
if (mb_strlen($address) > 255 || mb_strlen($notes) > 500) {
    jsonResponse(422, ['message' => 'El punto de entrega o las notas superan la longitud permitida.']);
}
if (!isAdultBirthDate($birthDateInput)) {
    jsonResponse(422, ['message' => 'Debes declarar una fecha de nacimiento que confirme que tienes 18 años o más.']);
}

$normalizedItems = [];
foreach ($items as $item) {
    $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
    if (!$productId || !$quantity || $quantity < 1 || $quantity > 100 || isset($normalizedItems[$productId])) {
        jsonResponse(422, ['message' => 'El carrito contiene cantidades inválidas.']);
    }
    $normalizedItems[$productId] = $quantity;
}
ksort($normalizedItems, SORT_NUMERIC);

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador', 'Cliente']);
$userId = (int) $actor['id'];
$isPaypal = $method === 'paypal';
$checkoutStage = 'prepare';
$paypalCaptureId = null;
$finalized = false;
$inTransaction = false;

/**
 * Respuesta idempotente para una compra que ya fue procesada.
 */
function checkoutExistingResponse(array $row, string $method): array
{
    $paid = in_array((string) $row['estado_pago'], ['APROBADO', 'CAPTURADO'], true);
    return [
        'success' => $paid,
        'orderId' => (int) $row['id'],
        'orderNumber' => (string) $row['numero'],
        'total' => (float) $row['total'],
        'paymentStatus' => (string) $row['estado_pago'],
        'orderStatus' => (string) $row['estado'],
        'paymentMethod' => $method,
        'saleId' => null,
        'invoiceAvailable' => $paid,
        'message' => 'Esta solicitud ya fue procesada.',
    ];
}

try {
    $paypalIntent = null;
    $expectedUsd = null;

    // 1. Para PayPal, validar la intención y el carrito sin abrir transacción.
    if ($isPaypal) {
        $checkoutStage = 'load_paypal_intent';
        $intentQuery = $connection->prepare(
            'SELECT clave_idempotencia, amount_cop, amount_usd, items_json, direccion_entrega, notas, estado, capture_id
             FROM paypal_checkout_intents WHERE paypal_order_id = ? AND usuario_id = ? LIMIT 1'
        );
        $intentQuery->bind_param('si', $paypalOrderId, $userId);
        $intentQuery->execute();
        $paypalIntent = $intentQuery->get_result()->fetch_assoc();
        $intentQuery->close();
        if (!$paypalIntent || $paypalIntent['clave_idempotencia'] !== $key) {
            throw new DomainException('La orden PayPal no corresponde a esta compra.');
        }
        if (!in_array($paypalIntent['estado'], ['CREATED', 'COMPLETED'], true)) {
            throw new DomainException('La orden PayPal ya no está disponible.');
        }
        $address = (string) $paypalIntent['direccion_entrega'];
        $notes = (string) $paypalIntent['notas'];

        $storedItems = json_decode((string) $paypalIntent['items_json'], true);
        $storedItems = is_array($storedItems) ? $storedItems : [];
        $storedItems = array_column($storedItems, 'quantity', 'id');
        ksort($storedItems, SORT_NUMERIC);
        if ($storedItems !== $normalizedItems) {
            throw new DomainException('El carrito ya no coincide con la orden PayPal.');
        }
    }

    // 2. Idempotencia: si ya existe un pedido finalizado, devolverlo sin cobrar de nuevo.
    $checkoutStage = 'check_existing_order';
    $existingQuery = $connection->prepare(
        'SELECT id, numero, total, estado, estado_pago
         FROM pedidos WHERE clave_idempotencia = ? AND usuario_id = ? LIMIT 1'
    );
    $existingQuery->bind_param('si', $key, $userId);
    $existingQuery->execute();
    $existingOrder = $existingQuery->get_result()->fetch_assoc();
    $existingQuery->close();
    if ($existingOrder && (string) $existingOrder['estado_pago'] !== 'PENDIENTE') {
        $connection->close();
        jsonResponse(200, checkoutExistingResponse($existingOrder, $method));
    }

    // 3. Validación previa sin locks: evita capturar un monto distinto al mostrado.
    $checkoutStage = 'validate_products';
    $validated = [];
    $totalCents = 0;
    foreach ($normalizedItems as $productId => $quantity) {
        $productQuery = $connection->prepare(
            'SELECT id, nombre, precio, stock FROM productos WHERE id = ? AND estado = 1 LIMIT 1'
        );
        $productQuery->bind_param('i', $productId);
        $productQuery->execute();
        $product = $productQuery->get_result()->fetch_assoc();
        $productQuery->close();
        if (!$product) {
            throw new DomainException('Uno de los productos ya no está disponible.');
        }
        if ((int) $product['stock'] < $quantity) {
            throw new DomainException('No hay stock suficiente para completar la compra.');
        }
        $priceCents = (int) round((float) $product['precio'] * 100);
        $totalCents += $priceCents * $quantity;
        $validated[$productId] = [
            'id' => (int) $product['id'],
            'name' => (string) $product['nombre'],
            'price' => $priceCents / 100,
            'quantity' => $quantity,
            'stock' => (int) $product['stock'],
        ];
    }
    $total = $totalCents / 100;
    if ($total <= 0) {
        throw new DomainException('El total del pedido no es válido.');
    }

    if ($isPaypal) {
        $configuration = paypalConfiguration();
        $expectedUsd = paypalAmountUsd($totalCents, (float) $configuration['copPerUsd']);
        if (abs((float) $paypalIntent['amount_cop'] - $total) > 0.001
            || number_format((float) $paypalIntent['amount_usd'], 2, '.', '') !== $expectedUsd) {
            throw new DomainException('El precio cambió desde que se inició el pago. Vuelve a intentarlo.');
        }
    }

    // 4. Cobro en PayPal fuera de cualquier transacción de base de datos.
    if ($isPaypal) {
        if ($paypalIntent['estado'] === 'COMPLETED' && !empty($paypalIntent['capture_id'])) {
            $paypalCaptureId = (string) $paypalIntent['capture_id'];
        } else {
            $checkoutStage = 'verify_paypal_order';
            $paypalOrder = paypalApiRequest('GET', '/v2/checkout/orders/' . rawurlencode($paypalOrderId));
            if (!paypalOrderIsApproved($paypalOrder, $paypalOrderId, $key, (string) $expectedUsd)) {
                throw new DomainException('PayPal todavía no ha aprobado el pago.');
            }

            if (($paypalOrder['status'] ?? '') === 'COMPLETED') {
                $paypalCaptureId = paypalCompletedCaptureId($paypalOrder, $paypalOrderId, $key, (string) $expectedUsd);
                if ($paypalCaptureId === null) {
                    throw new RuntimeException('PayPal no confirmó el cobro completo.');
                }
            } else {
                $checkoutStage = 'capture_paypal_order';
                try {
                    paypalApiRequest(
                        'POST',
                        '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture',
                        null,
                        ['PayPal-Request-Id: ' . substr(hash('sha256', 'capture-' . $key), 0, 36)]
                    );
                } catch (RuntimeException) {
                    // La captura pudo completarse aunque la respuesta se perdiera.
                    error_log('LiquorSoft PayPal capture response was inconclusive; checking order status.');
                }

                $checkoutStage = 'verify_paypal_capture';
                $capturedOrder = paypalApiRequest('GET', '/v2/checkout/orders/' . rawurlencode($paypalOrderId));
                $paypalCaptureId = paypalCompletedCaptureId($capturedOrder, $paypalOrderId, $key, (string) $expectedUsd);
                if ($paypalCaptureId === null) {
                    throw new RuntimeException('PayPal no confirmó el cobro completo.');
                }
            }
        }

        $paymentStatus = 'CAPTURADO';
        $orderStatus = 'CONFIRMADO';
        $reference = 'PP-' . $paypalCaptureId;
    } else {
        $paymentStatus = match ($outcome) {
            'approved' => 'APROBADO',
            'rejected' => 'RECHAZADO',
            'pending' => 'PENDIENTE',
            'cancelled' => 'CANCELADO',
            default => 'ERROR',
        };
        $orderStatus = $outcome === 'approved' ? 'CONFIRMADO' : ($outcome === 'cancelled' ? 'CANCELADO' : 'PENDIENTE');
        $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(8)));
    }

    // 5. Transacción local corta: solo base de datos, sin llamadas externas.
    $checkoutStage = 'begin_final_transaction';
    $connection->begin_transaction();
    $inTransaction = true;

    $checkoutStage = 'lock_order';
    $lockOrder = $connection->prepare(
        'SELECT id, numero, total, estado, estado_pago
         FROM pedidos WHERE clave_idempotencia = ? AND usuario_id = ? FOR UPDATE'
    );
    $lockOrder->bind_param('si', $key, $userId);
    $lockOrder->execute();
    $lockedOrder = $lockOrder->get_result()->fetch_assoc();
    $lockOrder->close();

    if ($lockedOrder && (string) $lockedOrder['estado_pago'] !== 'PENDIENTE') {
        $connection->rollback();
        $inTransaction = false;
        $connection->close();
        jsonResponse(200, checkoutExistingResponse($lockedOrder, $method));
    }

    $orderNumber = 'PED-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    if ($lockedOrder) {
        $saleQuery = $connection->prepare('SELECT id FROM ventas WHERE pedido_id = ? LIMIT 1');
        $saleQuery->bind_param('i', $lockedOrder['id']);
        $saleQuery->execute();
        $existingSale = $saleQuery->get_result()->fetch_assoc();
        $saleQuery->close();
        if ($existingSale) {
            $connection->rollback();
            $inTransaction = false;
            $connection->close();
            jsonResponse(200, checkoutExistingResponse($lockedOrder, $method));
        }

        $orderId = (int) $lockedOrder['id'];
        $orderNumber = (string) $lockedOrder['numero'];
        $checkoutStage = 'reset_pending_order';
        $resetOrder = $connection->prepare(
            "UPDATE pedidos
             SET subtotal = 0, descuento = 0, total = 0, estado = 'PENDIENTE', metodo_pago = ?,
                 direccion_entrega = ?, notas = ?, estado_pago = 'PENDIENTE',
                 referencia_pago = NULL, moneda = 'COP'
             WHERE id = ?"
        );
        $resetOrder->bind_param('sssi', $method, $address, $notes, $orderId);
        if (!$resetOrder->execute()) throw new RuntimeException('No fue posible reiniciar el pedido pendiente.');
        $resetOrder->close();

        $clearDetails = $connection->prepare('DELETE FROM detalle_pedido WHERE pedido_id = ?');
        $clearDetails->bind_param('i', $orderId);
        if (!$clearDetails->execute()) throw new RuntimeException('No fue posible limpiar el detalle anterior.');
        $clearDetails->close();
    } else {
        $checkoutStage = 'create_local_order';
        $order = $connection->prepare(
            "INSERT INTO pedidos (numero, usuario_id, subtotal, descuento, total, estado, metodo_pago,
              direccion_entrega, notas, clave_idempotencia, estado_pago, moneda)
             VALUES (?, ?, 0, 0, 0, 'PENDIENTE', ?, ?, ?, ?, 'PENDIENTE', 'COP')"
        );
        $order->bind_param('sissss', $orderNumber, $userId, $method, $address, $notes, $key);
        if (!$order->execute()) {
            if ($order->errno === 1062) {
                $connection->rollback();
                $inTransaction = false;
                $existing = $connection->prepare(
                    'SELECT id, numero, total, estado, estado_pago FROM pedidos WHERE clave_idempotencia = ? AND usuario_id = ?'
                );
                $existing->bind_param('si', $key, $userId);
                $existing->execute();
                $row = $existing->get_result()->fetch_assoc();
                $existing->close();
                $connection->close();
                if ($row) {
                    jsonResponse(200, checkoutExistingResponse($row, $method));
                }
                jsonResponse(409, ['message' => 'Esta compra ya fue registrada. Revisa tus pedidos.']);
            }
            throw new RuntimeException('No fue posible iniciar el pedido.');
        }
        $orderId = (int) $connection->insert_id;
        $order->close();
    }

    // 6. Bloquear y revalidar precios/stock justo antes de descontar.
    $checkoutStage = 'relock_products';
    $lockedItems = [];
    $lockedTotalCents = 0;
    foreach ($normalizedItems as $productId => $quantity) {
        $productQuery = $connection->prepare(
            'SELECT id, nombre, precio, stock FROM productos WHERE id = ? AND estado = 1 FOR UPDATE'
        );
        $productQuery->bind_param('i', $productId);
        $productQuery->execute();
        $product = $productQuery->get_result()->fetch_assoc();
        $productQuery->close();
        if (!$product) {
            throw new DomainException('Uno de los productos ya no está disponible.');
        }
        if ((int) $product['stock'] < $quantity) {
            throw new DomainException('El inventario cambió y ya no alcanza para completar el pedido.');
        }
        $priceCents = (int) round((float) $product['precio'] * 100);
        if ($priceCents !== (int) round(((float) $validated[$productId]['price']) * 100)) {
            throw new DomainException('El precio cambió mientras confirmabas la compra. Vuelve a intentarlo.');
        }
        $lockedTotalCents += $priceCents * $quantity;
        $lockedItems[$productId] = [
            'id' => (int) $product['id'],
            'name' => (string) $product['nombre'],
            'price' => $priceCents / 100,
            'quantity' => $quantity,
            'stock' => (int) $product['stock'],
        ];
    }
    $total = $lockedTotalCents / 100;

    $updateOrder = $connection->prepare('UPDATE pedidos SET subtotal = ?, total = ? WHERE id = ?');
    $checkoutStage = 'persist_order_details';
    $updateOrder->bind_param('ddi', $total, $total, $orderId);
    if (!$updateOrder->execute()) throw new RuntimeException('No fue posible guardar el total del pedido.');
    $updateOrder->close();

    $detail = $connection->prepare(
        'INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario, nombre_producto) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($lockedItems as $item) {
        $productId = $item['id'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $productName = $item['name'];
        $detail->bind_param('iiids', $orderId, $productId, $quantity, $price, $productName);
        if (!$detail->execute()) throw new RuntimeException('No fue posible guardar el detalle del pedido.');
    }
    $detail->close();

    $saleId = null;
    if ($outcome === 'approved') {
        $checkoutStage = 'persist_sale_and_inventory';
        $sale = $connection->prepare(
            "INSERT INTO ventas (subtotal, descuento, total, estado, metodo_pago, notas, usuario_id, pedido_id)
             VALUES (?, 0, ?, 'completada', ?, ?, ?, ?)"
        );
        $sale->bind_param('ddssii', $total, $total, $method, $notes, $userId, $orderId);
        if (!$sale->execute()) throw new RuntimeException('No fue posible registrar la venta.');
        $saleId = (int) $connection->insert_id;
        $sale->close();

        $saleDetail = $connection->prepare(
            'INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)'
        );
        $stockUpdate = $connection->prepare('UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?');
        $movement = $connection->prepare(
            "INSERT INTO movimientos_inventario (producto_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, referencia, motivo)
             VALUES (?, ?, 'SALIDA', ?, ?, ?, ?, 'Compra de pedido pagado')"
        );
        foreach ($lockedItems as $item) {
            $productId = $item['id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $saleDetail->bind_param('iiid', $saleId, $productId, $quantity, $price);
            if (!$saleDetail->execute()) throw new RuntimeException('No fue posible guardar el detalle de venta.');
            $stockUpdate->bind_param('iii', $quantity, $productId, $quantity);
            if (!$stockUpdate->execute() || $stockUpdate->affected_rows !== 1) {
                throw new DomainException('El inventario cambió y ya no alcanza para completar el pedido.');
            }
            $after = $item['stock'] - $quantity;
            $movement->bind_param('iiiiis', $productId, $userId, $quantity, $item['stock'], $after, $orderNumber);
            if (!$movement->execute()) throw new RuntimeException('No fue posible guardar el movimiento de inventario.');
        }
        $saleDetail->close();
        $stockUpdate->close();
        $movement->close();
    }

    if ($isPaypal) {
        $checkoutStage = 'persist_paypal_capture';
        $completeIntent = $connection->prepare(
            "UPDATE paypal_checkout_intents SET estado = 'COMPLETED', capture_id = ?
             WHERE paypal_order_id = ? AND usuario_id = ?"
        );
        $completeIntent->bind_param('ssi', $paypalCaptureId, $paypalOrderId, $userId);
        if (!$completeIntent->execute()) throw new RuntimeException('No fue posible guardar el estado del pago.');
        $completeIntent->close();
    }

    $checkoutStage = 'persist_order_status';
    $setPayment = $connection->prepare(
        'UPDATE pedidos SET subtotal = ?, total = ?, estado = ?, estado_pago = ?, referencia_pago = ? WHERE id = ?'
    );
    $setPayment->bind_param('ddsssi', $total, $total, $orderStatus, $paymentStatus, $reference, $orderId);
    if (!$setPayment->execute()) throw new RuntimeException('No fue posible guardar el estado del pedido.');
    $setPayment->close();

    $history = $connection->prepare(
        'INSERT INTO historial_pedido (pedido_id, usuario_id, estado_anterior, estado_nuevo, metodo_entrega_nuevo, detalle_entrega)
         VALUES (?, ?, NULL, ?, \'POR_COORDINAR\', NULL)'
    );
    $history->bind_param('iis', $orderId, $userId, $orderStatus);
    if (!$history->execute()) throw new RuntimeException('No fue posible guardar el historial del pedido.');
    $history->close();

    $checkoutStage = 'commit_transaction';
    $connection->commit();
    $finalized = true;
    $inTransaction = false;

    $clientTitle = $outcome === 'approved' ? 'Compra registrada y pago confirmado' : 'Compra registrada';
    $formattedTotal = '$' . number_format($total, 0, ',', '.') . ' COP';
    $clientMsg = $outcome === 'approved'
        ? "Tu pedido #$orderNumber ha sido confirmado con éxito por {$formattedTotal}. Pronto iniciaremos su preparación."
        : "Tu pedido #$orderNumber ha sido registrado con estado {$orderStatus}. Total: {$formattedTotal}.";
    $adminTitle = 'Nueva compra recibida';
    $adminMsg = "Se ha registrado un nuevo pedido #$orderNumber por {$formattedTotal} que requiere revisión.";

    // Notificaciones secundarias: nunca revierten un pago ya capturado.
    try {
        createNotification($connection, $userId, 'COMPRA_REGISTRADA', $clientTitle, $clientMsg, $orderId);
        notifyAdmins($connection, 'NUEVA_COMPRA_ADMIN', $adminTitle, $adminMsg, $orderId);
    } catch (Throwable $notificationError) {
        error_log('LiquorSoft checkout notifications failed after commit: ' . $notificationError->getMessage());
    }

    $connection->close();
    $messages = [
        'approved' => $method === 'paypal'
            ? '¡Compra completada correctamente! Tu pago ha sido confirmado y tu pedido ha quedado registrado.'
            : 'Pago de prueba aprobado por el simulador de desarrollo. El pedido fue confirmado.',
        'rejected' => 'El pago de prueba fue rechazado. No se descontó inventario.',
        'pending' => 'El pago de prueba quedó pendiente. No se descontó inventario.',
        'cancelled' => 'El pago fue cancelado. No se descontó inventario.',
        'error' => 'El simulador registró un error. No se descontó inventario.',
    ];
    jsonResponse(201, [
        'success' => $outcome === 'approved',
        'orderId' => $orderId,
        'orderNumber' => $orderNumber,
        'saleId' => $saleId,
        'total' => $total,
        'paymentMethod' => $method,
        'paymentStatus' => $paymentStatus,
        'orderStatus' => $orderStatus,
        'invoiceAvailable' => $outcome === 'approved',
        'message' => $messages[$outcome],
    ]);
} catch (Throwable $error) {
    if ($inTransaction && $connection->connect_errno === 0) {
        try {
            $connection->rollback();
        } catch (Throwable) {
            // La conexión pudo haberse perdido; el reembolso de abajo sigue siendo prioritario.
        }
    }

    $refunded = false;
    if ($isPaypal && $paypalCaptureId !== null && !$finalized) {
        // Otra solicitud concurrente pudo haber finalizado el mismo pedido.
        // Nunca reembolsar una captura que ya tiene una venta local confirmada.
        if ($connection->connect_errno === 0) {
            $paidQuery = $connection->prepare(
                "SELECT id, numero, total, estado, estado_pago FROM pedidos
                 WHERE clave_idempotencia = ? AND usuario_id = ?
                   AND estado_pago IN ('APROBADO', 'CAPTURADO') LIMIT 1"
            );
            if ($paidQuery) {
                $paidQuery->bind_param('si', $key, $userId);
                $paidQuery->execute();
                $paidOrder = $paidQuery->get_result()->fetch_assoc();
                $paidQuery->close();
                if ($paidOrder) {
                    $connection->close();
                    jsonResponse(200, checkoutExistingResponse($paidOrder, $method));
                }
            }
        }

        try {
            paypalRefundCapture($paypalCaptureId, $key);
            $refunded = true;
            error_log('LiquorSoft checkout failed after PayPal capture; capture refunded automatically.');
        } catch (Throwable $refundError) {
            error_log(
                'CRITICAL LiquorSoft PayPal capture requires manual refund. Capture: ' . $paypalCaptureId
                . ' Key: ' . $key . ' Error: ' . $refundError->getMessage()
            );
            try {
                notifyAdmins(
                    $connection,
                    'PAYPAL_REEMBOLSO_FALLIDO',
                    'Reembolso PayPal pendiente',
                    "La captura {$paypalCaptureId} se cobró pero el pedido no pudo registrarse. Revisa PayPal y reembolsa manualmente. Clave: {$key}.",
                    null
                );
            } catch (Throwable) {
                // Sin base de datos no se puede notificar; el log crítico es la última defensa.
            }
        }
    }

    if ($connection->connect_errno === 0) {
        $connection->close();
    }

    error_log('LiquorSoft checkout failed at ' . $checkoutStage . ' (' . get_class($error) . ').');

    if ($refunded) {
        jsonResponse(409, [
            'message' => 'El pago fue reembolsado automáticamente porque el inventario o el precio cambió antes de registrar el pedido. Puedes intentarlo de nuevo.',
        ]);
    }
    if ($isPaypal && $paypalCaptureId !== null && !$finalized) {
        jsonResponse(500, [
            'message' => 'El pago se cobró pero no pudimos registrar el pedido. Contáctanos con tu número de orden de PayPal para resolverlo.',
        ]);
    }
    if ($error instanceof DomainException) {
        jsonResponse(422, ['message' => $error->getMessage()]);
    }
    jsonResponse(500, ['message' => 'No fue posible procesar el pedido. Intenta nuevamente.']);
}
