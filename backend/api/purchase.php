<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paypal.php';

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

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador', 'Cliente']);
$userId = (int) $actor['id'];
$checkoutStage = 'begin_transaction';

try {
    $connection->begin_transaction();
    $paypalIntent = null;
    $paypalCaptureId = null;
    $expectedUsd = null;
    if ($method === 'paypal') {
        $checkoutStage = 'load_paypal_intent';
        $intentQuery = $connection->prepare(
            'SELECT clave_idempotencia, amount_cop, amount_usd, items_json, direccion_entrega, notas, estado, capture_id
             FROM paypal_checkout_intents WHERE paypal_order_id = ? AND usuario_id = ? FOR UPDATE'
        );
        $intentQuery->bind_param('si', $paypalOrderId, $userId);
        $intentQuery->execute();
        $paypalIntent = $intentQuery->get_result()->fetch_assoc();
        $intentQuery->close();
        if (!$paypalIntent || $paypalIntent['clave_idempotencia'] !== $key) {
            throw new DomainException('La orden PayPal no corresponde a esta compra.');
        }
        $address = (string) $paypalIntent['direccion_entrega'];
        $notes = (string) $paypalIntent['notas'];
        if (!in_array($paypalIntent['estado'], ['CREATED', 'COMPLETED'], true)) {
            throw new DomainException('La orden PayPal ya no está disponible.');
        }
        $requestItems = [];
        foreach ($items as $item) {
            $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (!$productId || !$quantity || $quantity < 1 || $quantity > 100 || isset($requestItems[$productId])) {
                throw new DomainException('El carrito contiene cantidades inválidas.');
            }
            $requestItems[$productId] = $quantity;
        }
        ksort($requestItems, SORT_NUMERIC);
        $storedItems = json_decode((string) $paypalIntent['items_json'], true);
        $storedItems = is_array($storedItems) ? $storedItems : [];
        $storedItems = array_column($storedItems, 'quantity', 'id');
        ksort($storedItems, SORT_NUMERIC);
        if ($storedItems !== $requestItems) {
            throw new DomainException('El carrito ya no coincide con la orden PayPal.');
        }
    }

    $orderNumber = 'PED-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $order = $connection->prepare(
        "INSERT INTO pedidos (numero, usuario_id, subtotal, descuento, total, estado, metodo_pago,
          direccion_entrega, notas, clave_idempotencia, estado_pago, moneda)
         VALUES (?, ?, 0, 0, 0, 'PENDIENTE', ?, ?, ?, ?, 'PENDIENTE', 'COP')"
    );
    $checkoutStage = 'create_local_order';
    $order->bind_param('sissss', $orderNumber, $userId, $method, $address, $notes, $key);
    if (!$order->execute()) {
        if ($order->errno === 1062) {
            $connection->rollback();
            $existing = $connection->prepare(
                'SELECT id, numero, total, estado, estado_pago FROM pedidos WHERE clave_idempotencia = ? AND usuario_id = ?'
            );
            $existing->bind_param('si', $key, $userId);
            $existing->execute();
            $row = $existing->get_result()->fetch_assoc();
            $existing->close();
            if ($row) {
                $connection->close();
                jsonResponse(200, [
                    'success' => in_array($row['estado_pago'], ['APROBADO', 'CAPTURADO'], true),
                    'orderId' => (int) $row['id'], 'orderNumber' => $row['numero'],
                    'total' => (float) $row['total'], 'paymentStatus' => $row['estado_pago'],
                    'orderStatus' => $row['estado'], 'paymentMethod' => $method,
                    'saleId' => null,
                    'invoiceAvailable' => in_array($row['estado_pago'], ['APROBADO', 'CAPTURADO'], true),
                    'message' => 'Esta solicitud ya fue procesada.',
                ]);
            }
        }
        throw new RuntimeException('No fue posible iniciar el pedido.');
    }
    $orderId = (int) $connection->insert_id;
    $order->close();

    $validated = [];
    $totalCents = 0;
    $checkoutStage = 'validate_products';
    foreach ($items as $item) {
        $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        if (!$productId || !$quantity || $quantity < 1 || $quantity > 100) {
            throw new DomainException('El carrito contiene cantidades inválidas.');
        }
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
            throw new DomainException('No hay stock suficiente para completar la compra.');
        }
        $priceCents = (int) round((float) $product['precio'] * 100);
        $totalCents += $priceCents * $quantity;
        $validated[] = [
            'id' => (int) $product['id'], 'name' => (string) $product['nombre'],
            'price' => $priceCents / 100, 'quantity' => $quantity, 'stock' => (int) $product['stock'],
        ];
    }
    $total = $totalCents / 100;
    if ($method === 'paypal') {
        $checkoutStage = 'verify_paypal_order';
        $configuration = paypalConfiguration();
        $expectedUsd = paypalAmountUsd($totalCents, (float) $configuration['copPerUsd']);
        if (abs((float) $paypalIntent['amount_cop'] - $total) > 0.001
            || number_format((float) $paypalIntent['amount_usd'], 2, '.', '') !== $expectedUsd) {
            throw new DomainException('El precio cambió desde que se inició el pago. Vuelve a intentarlo.');
        }

        $paypalOrder = paypalApiRequest('GET', '/v2/checkout/orders/' . rawurlencode($paypalOrderId));
        if (!paypalOrderIsApproved($paypalOrder, $paypalOrderId, $key, $expectedUsd)) {
            throw new DomainException('PayPal todavía no ha aprobado el pago.');
        }

        if (($paypalOrder['status'] ?? '') === 'COMPLETED') {
            $paypalCaptureId = paypalCompletedCaptureId($paypalOrder, $paypalOrderId, $key, $expectedUsd);
            if ($paypalCaptureId === null) {
                throw new RuntimeException('PayPal no confirmó el cobro completo.');
            }
        }
    }
    $updateOrder = $connection->prepare('UPDATE pedidos SET subtotal = ?, total = ? WHERE id = ?');
    $checkoutStage = 'persist_order_details';
    $updateOrder->bind_param('ddi', $total, $total, $orderId);
    if (!$updateOrder->execute()) throw new RuntimeException('No fue posible guardar el total del pedido.');
    $updateOrder->close();

    $detail = $connection->prepare(
        'INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario, nombre_producto) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($validated as $item) {
        $productId = $item['id']; $quantity = $item['quantity']; $price = $item['price']; $productName = $item['name'];
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
        foreach ($validated as $item) {
            $productId = $item['id']; $quantity = $item['quantity']; $price = $item['price'];
            $saleDetail->bind_param('iiid', $saleId, $productId, $quantity, $price);
            if (!$saleDetail->execute()) throw new RuntimeException('No fue posible guardar el detalle de venta.');
            $stockUpdate->bind_param('iii', $item['quantity'], $item['id'], $item['quantity']);
            if (!$stockUpdate->execute() || $stockUpdate->affected_rows !== 1) {
                throw new DomainException('El inventario cambió y ya no alcanza para completar el pedido.');
            }
            $after = $item['stock'] - $item['quantity'];
            $movement->bind_param('iiiiis', $productId, $userId, $quantity, $item['stock'], $after, $orderNumber);
            if (!$movement->execute()) throw new RuntimeException('No fue posible guardar el movimiento de inventario.');
        }
        $saleDetail->close(); $stockUpdate->close(); $movement->close();
    }

    if ($method === 'paypal' && $paypalCaptureId === null) {
        $checkoutStage = 'capture_paypal_order';
        try {
            paypalApiRequest(
                'POST',
                '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture',
                null,
                ['PayPal-Request-Id: ' . substr(hash('sha256', 'capture-' . $key), 0, 36)]
            );
        } catch (RuntimeException) {
            error_log('PayPal capture response was inconclusive; checking the order status before retrying.');
        }
        $checkoutStage = 'verify_paypal_capture';
        $capturedOrder = paypalApiRequest('GET', '/v2/checkout/orders/' . rawurlencode($paypalOrderId));
        $paypalCaptureId = paypalCompletedCaptureId($capturedOrder, $paypalOrderId, $key, (string) $expectedUsd);
        if ($paypalCaptureId === null) {
            throw new RuntimeException('PayPal no confirmó el cobro completo.');
        }
    }
    if ($method === 'paypal') {
        $checkoutStage = 'persist_paypal_capture';
        $completeIntent = $connection->prepare(
            "UPDATE paypal_checkout_intents SET estado = 'COMPLETED', capture_id = ? WHERE paypal_order_id = ?"
        );
        $completeIntent->bind_param('ss', $paypalCaptureId, $paypalOrderId);
        if (!$completeIntent->execute()) throw new RuntimeException('No fue posible guardar el estado del pago.');
        $completeIntent->close();
    }

    $paymentStatus = $method === 'paypal' ? 'CAPTURADO' : match ($outcome) {
        'approved' => 'APROBADO', 'rejected' => 'RECHAZADO', 'pending' => 'PENDIENTE',
        'cancelled' => 'CANCELADO', default => 'ERROR',
    };
    $orderStatus = $outcome === 'approved' ? 'CONFIRMADO' : ($outcome === 'cancelled' ? 'CANCELADO' : 'PENDIENTE');
    $reference = $method === 'paypal'
        ? 'PP-' . $paypalCaptureId
        : 'SIM-' . strtoupper(bin2hex(random_bytes(8)));
    $checkoutStage = 'persist_order_status';
    $setPayment = $connection->prepare('UPDATE pedidos SET estado = ?, estado_pago = ?, referencia_pago = ? WHERE id = ?');
    $setPayment->bind_param('sssi', $orderStatus, $paymentStatus, $reference, $orderId);
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
        'success' => $outcome === 'approved', 'orderId' => $orderId, 'orderNumber' => $orderNumber,
        'saleId' => $saleId, 'total' => $total, 'paymentMethod' => $method,
        'paymentStatus' => $paymentStatus, 'orderStatus' => $orderStatus,
        'invoiceAvailable' => $outcome === 'approved', 'message' => $messages[$outcome],
    ]);
} catch (Throwable $error) {
    if ($connection->connect_errno === 0) $connection->rollback();
    $connection->close();
    error_log('LiquorSoft checkout failed at ' . $checkoutStage . ' (' . get_class($error) . ').');
    $isBusinessError = $error instanceof DomainException;
    jsonResponse($isBusinessError ? 422 : 500, [
        'message' => $isBusinessError ? $error->getMessage() : 'No fue posible procesar el pedido. Intenta nuevamente.',
    ]);
}
