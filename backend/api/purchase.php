<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}
requireCsrfToken();

$payload = json_decode(file_get_contents('php://input'), true);
$items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
$method = (string) ($payload['paymentMethod'] ?? '');
$outcome = (string) ($payload['simulationOutcome'] ?? '');
$key = strtolower(trim((string) ($payload['idempotencyKey'] ?? '')));
$address = trim((string) ($payload['deliveryAddress'] ?? ''));
$birthDateInput = trim((string) ($payload['birthDate'] ?? ''));
$notes = trim((string) ($payload['notes'] ?? ''));
if (!paymentSimulatorEnabled()) {
    jsonResponse(503, ['message' => 'No hay una pasarela de pago configurada para este entorno.']);
}
if ($method !== 'nequi_simulado' || !in_array($outcome, ['approved', 'rejected', 'pending', 'cancelled', 'error'], true)) {
    jsonResponse(422, ['message' => 'Selecciona Nequi simulado y un resultado de prueba válido.']);
}
if (!$items || count($items) > 40 || strlen($key) !== 36 || !preg_match('/^[0-9a-f-]{36}$/', $key)) {
    jsonResponse(422, ['message' => 'El carrito o la clave de compra no son válidos.']);
}
if ($address === '' || mb_strlen($address) > 255 || mb_strlen($notes) > 500) {
    jsonResponse(422, ['message' => 'Escribe una dirección de entrega válida.']);
}
if (!isAdultBirthDate($birthDateInput)) {
    jsonResponse(422, ['message' => 'Debes declarar una fecha de nacimiento que confirme que tienes 18 años o más.']);
}

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador', 'Cliente']);
$userId = (int) $actor['id'];

try {
    $connection->begin_transaction();
    $orderNumber = 'PED-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $order = $connection->prepare(
        "INSERT INTO pedidos (numero, usuario_id, subtotal, descuento, total, estado, metodo_pago,
          direccion_entrega, notas, clave_idempotencia, estado_pago, moneda)
         VALUES (?, ?, 0, 0, 0, 'PENDIENTE', ?, ?, ?, ?, 'PENDIENTE', 'COP')"
    );
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
                    'success' => $row['estado_pago'] === 'APROBADO',
                    'orderId' => (int) $row['id'], 'orderNumber' => $row['numero'],
                    'total' => (float) $row['total'], 'paymentStatus' => $row['estado_pago'],
                    'orderStatus' => $row['estado'], 'message' => 'Esta solicitud ya fue procesada.',
                ]);
            }
        }
        throw new RuntimeException('No fue posible iniciar el pedido.');
    }
    $orderId = (int) $connection->insert_id;
    $order->close();

    $validated = [];
    $totalCents = 0;
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
    $updateOrder = $connection->prepare('UPDATE pedidos SET subtotal = ?, total = ? WHERE id = ?');
    $updateOrder->bind_param('ddi', $total, $total, $orderId);
    $updateOrder->execute();
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

    $paymentStatus = match ($outcome) {
        'approved' => 'APROBADO', 'rejected' => 'RECHAZADO', 'pending' => 'PENDIENTE',
        'cancelled' => 'CANCELADO', default => 'ERROR',
    };
    $orderStatus = $outcome === 'approved' ? 'CONFIRMADO' : ($outcome === 'cancelled' ? 'CANCELADO' : 'PENDIENTE');
    $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(8)));
    $setPayment = $connection->prepare('UPDATE pedidos SET estado = ?, estado_pago = ?, referencia_pago = ? WHERE id = ?');
    $setPayment->bind_param('sssi', $orderStatus, $paymentStatus, $reference, $orderId);
    $setPayment->execute();
    $setPayment->close();

    $saleId = null;
    if ($outcome === 'approved') {
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
             VALUES (?, ?, 'SALIDA', ?, ?, ?, ?, 'Compra de pedido pagado (simulador de desarrollo)')"
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

    $connection->commit();
    $connection->close();
    $messages = [
        'approved' => 'Pago de prueba aprobado por el simulador de desarrollo. El pedido fue confirmado.',
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
    error_log('LiquorSoft checkout failed: ' . $error->getMessage());
    $isBusinessError = $error instanceof DomainException;
    jsonResponse($isBusinessError ? 422 : 500, [
        'message' => $isBusinessError ? $error->getMessage() : 'No fue posible procesar el pedido. Intenta nuevamente.',
    ]);
}
