<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$payload = json_decode(file_get_contents('php://input'), true);
$items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
$paymentMethod = (string) ($payload['paymentMethod'] ?? '');
$notes = trim((string) ($payload['notes'] ?? ''));
if (!$items || !in_array($paymentMethod, ['nequi', 'paypal', 'efectivo'], true)) {
    jsonResponse(422, ['message' => 'El carrito y el método de pago son obligatorios.']);
}
if (mb_strlen($notes) > 500) {
    jsonResponse(422, ['message' => 'Las notas no pueden superar 500 caracteres.']);
}

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador', 'Cliente']);
$userId = (int) $actor['id'];
$hasPayment = tableHasColumn($connection, 'ventas', 'metodo_pago');
$hasNotes = tableHasColumn($connection, 'ventas', 'notas');

try {
    $connection->begin_transaction();
    $validatedItems = [];
    $total = 0.0;

    foreach ($items as $item) {
        $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        if (!$productId || !$quantity || $quantity < 1) {
            throw new RuntimeException('El carrito contiene un producto inválido.');
        }

        $statement = $connection->prepare(
            'SELECT id, precio, stock FROM productos WHERE id = ? AND estado = 1 FOR UPDATE'
        );
        if (!$statement) {
            throw new RuntimeException('No fue posible validar los productos.');
        }
        $statement->bind_param('i', $productId);
        $statement->execute();
        $product = $statement->get_result()->fetch_assoc() ?: null;
        $statement->close();

        if (!$product) {
            throw new RuntimeException('Uno de los productos ya no está disponible.');
        }
        if ((int) $product['stock'] < $quantity) {
            throw new RuntimeException('No hay stock suficiente para completar la compra.');
        }

        $unitPrice = (float) $product['precio'];
        $total += $unitPrice * $quantity;
        $validatedItems[] = [
            'id' => (int) $product['id'],
            'quantity' => $quantity,
            'price' => $unitPrice,
            'stockBefore' => (int) $product['stock'],
        ];
    }

    $orderNumber = 'PED-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $orderStatement = $connection->prepare(
        'INSERT INTO pedidos (numero, usuario_id, subtotal, total, estado, metodo_pago, notas)
         VALUES (?, ?, ?, ?, \'PENDIENTE\', ?, ?)'
    );
    if (!$orderStatement) {
        throw new RuntimeException('No fue posible crear el pedido.');
    }
    $orderStatement->bind_param('siddss', $orderNumber, $userId, $total, $total, $paymentMethod, $notes);
    if (!$orderStatement->execute()) {
        throw new RuntimeException('No fue posible crear el pedido.');
    }
    $orderId = $connection->insert_id;
    $orderStatement->close();

    $columns = ['total', 'estado', 'usuario_id', 'pedido_id'];
    $placeholders = ['?', "'completada'", '?', '?'];
    $types = 'dii';
    $values = [&$total, &$userId, &$orderId];
    if ($hasPayment) {
        $columns[] = 'metodo_pago';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = &$paymentMethod;
    }
    if ($hasNotes && $notes !== '') {
        $columns[] = 'notas';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = &$notes;
    }

    $sql = 'INSERT INTO ventas (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $saleStatement = $connection->prepare($sql);
    if (!$saleStatement) {
        throw new RuntimeException('No fue posible registrar la venta.');
    }
    $saleStatement->bind_param($types, ...$values);
    if (!$saleStatement->execute()) {
        throw new RuntimeException('No fue posible registrar la venta.');
    }
    $saleId = $connection->insert_id;
    $saleStatement->close();

    $detailStatement = $connection->prepare(
        'INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)'
    );
    $orderDetailStatement = $connection->prepare(
        'INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)'
    );
    $stockStatement = $connection->prepare(
        'UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?'
    );
    $movementStatement = $connection->prepare(
        'INSERT INTO movimientos_inventario
         (producto_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo)
         VALUES (?, ?, \'SALIDA\', ?, ?, ?, \'Venta registrada\')'
    );
    if (!$detailStatement || !$orderDetailStatement || !$stockStatement || !$movementStatement) {
        throw new RuntimeException('No fue posible registrar el detalle de la venta.');
    }

    foreach ($validatedItems as $item) {
        $detailStatement->bind_param('iiid', $saleId, $item['id'], $item['quantity'], $item['price']);
        if (!$detailStatement->execute()) {
            throw new RuntimeException('No fue posible registrar el detalle de la venta.');
        }
        $orderDetailStatement->bind_param('iiid', $orderId, $item['id'], $item['quantity'], $item['price']);
        if (!$orderDetailStatement->execute()) {
            throw new RuntimeException('No fue posible registrar el detalle del pedido.');
        }
        $stockStatement->bind_param('iii', $item['quantity'], $item['id'], $item['quantity']);
        if (!$stockStatement->execute() || $stockStatement->affected_rows !== 1) {
            throw new RuntimeException('El stock cambió mientras se procesaba la compra.');
        }
        $stockBefore = (int) $item['stockBefore'];
        $stockAfter = $stockBefore - $item['quantity'];
        $movementStatement->bind_param('iiiii', $item['id'], $userId, $item['quantity'], $stockBefore, $stockAfter);
        if (!$movementStatement->execute()) {
            throw new RuntimeException('No fue posible registrar el movimiento de venta.');
        }
    }

    $detailStatement->close();
    $orderDetailStatement->close();
    $stockStatement->close();
    $movementStatement->close();
    $connection->commit();
    $connection->close();
    jsonResponse(201, [
        'success' => true,
        'saleId' => $saleId,
        'orderId' => $orderId,
        'orderNumber' => $orderNumber,
        'total' => $total,
        'paymentMethod' => $paymentMethod,
        'message' => 'Pedido registrado. El cobro externo sigue pendiente de pasarela.',
    ]);
} catch (Throwable $error) {
    $connection->rollback();
    $connection->close();
    error_log('LiquorSoft purchase failed: ' . $error->getMessage());
    jsonResponse(422, ['message' => $error->getMessage()]);
}
