<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $connection->query(
        'SELECT c.id, c.numero_factura AS invoice, c.fecha_compra AS purchaseDate,
                c.total, c.estado AS status, p.nombre_empresa AS supplier
         FROM compras c INNER JOIN proveedores p ON p.id = c.proveedor_id
         ORDER BY c.fecha_compra DESC, c.id DESC LIMIT 100'
    );
    if (!$result) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar las compras.']);
    }
    $purchases = [];
    while ($row = $result->fetch_assoc()) {
        $purchases[] = [
            'id' => (int) $row['id'],
            'invoice' => (string) ($row['invoice'] ?? ''),
            'purchaseDate' => (string) $row['purchaseDate'],
            'total' => (float) $row['total'],
            'status' => (string) $row['status'],
            'supplier' => (string) $row['supplier'],
        ];
    }
    $connection->close();
    jsonResponse(200, ['purchases' => $purchases]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $connection->close();
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$body = json_decode(file_get_contents('php://input'), true);
$supplierId = filter_var($body['supplierId'] ?? null, FILTER_VALIDATE_INT);
$items = is_array($body['items'] ?? null) ? $body['items'] : [];
$invoice = trim((string) ($body['invoice'] ?? ''));
$purchaseDate = trim((string) ($body['purchaseDate'] ?? date('Y-m-d')));
$observation = trim((string) ($body['observation'] ?? ''));
if (!$supplierId || !$items || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
    $connection->close();
    jsonResponse(422, ['message' => 'Proveedor, fecha y productos son obligatorios.']);
}

try {
    $connection->begin_transaction();
    $supplier = $connection->prepare('SELECT id FROM proveedores WHERE id = ? AND COALESCE(estado, 1) = 1');
    $supplier->bind_param('i', $supplierId);
    $supplier->execute();
    if (!$supplier->get_result()->fetch_assoc()) {
        throw new RuntimeException('El proveedor no existe o está inactivo.');
    }
    $supplier->close();

    $validated = [];
    $subtotal = 0.0;
    foreach ($items as $item) {
        $productId = filter_var($item['productId'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        $cost = filter_var($item['cost'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!$productId || !$quantity || $quantity < 1 || $cost === false || $cost < 0) {
            throw new RuntimeException('La compra contiene un producto, cantidad o costo inválido.');
        }
        $product = $connection->prepare('SELECT id, stock FROM productos WHERE id = ? AND estado = 1 FOR UPDATE');
        $product->bind_param('i', $productId);
        $product->execute();
        $row = $product->get_result()->fetch_assoc();
        $product->close();
        if (!$row) {
            throw new RuntimeException('Uno de los productos no existe o está inactivo.');
        }
        $expiry = trim((string) ($item['expiryDate'] ?? ''));
        if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
            throw new RuntimeException('La fecha de vencimiento no es válida.');
        }
        $lineTotal = (float) $cost * $quantity;
        $subtotal += $lineTotal;
        $validated[] = ['id' => $productId, 'quantity' => $quantity, 'cost' => (float) $cost, 'stock' => (int) $row['stock'], 'batch' => trim((string) ($item['batch'] ?? '')), 'expiry' => $expiry !== '' ? $expiry : null];
    }

    $purchase = $connection->prepare(
        'INSERT INTO compras (numero_factura, proveedor_id, usuario_id, fecha_compra, subtotal, total, observacion)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $purchase->bind_param('siisdds', $invoice, $supplierId, $actor['id'], $purchaseDate, $subtotal, $subtotal, $observation);
    if (!$purchase->execute()) {
        throw new RuntimeException('No fue posible registrar la compra.');
    }
    $purchaseId = $connection->insert_id;
    $purchase->close();

    $detail = $connection->prepare('INSERT INTO detalle_compra (compra_id, producto_id, cantidad, costo_unitario, lote_id) VALUES (?, ?, ?, ?, ?)');
    $update = $connection->prepare('UPDATE productos SET stock = stock + ?, precio_compra = ? WHERE id = ?');
    $lot = $connection->prepare('INSERT INTO lotes (producto_id, proveedor_id, codigo, fecha_ingreso, fecha_vencimiento, cantidad_inicial, cantidad_disponible) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $movement = $connection->prepare('INSERT INTO movimientos_inventario (producto_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, lote_id, referencia, motivo) VALUES (?, ?, \'ENTRADA\', ?, ?, ?, ?, ?, \'Compra recibida\')');
    if (!$detail || !$update || !$lot || !$movement) {
        throw new RuntimeException('No fue posible preparar la entrada de inventario.');
    }

    foreach ($validated as $item) {
        $batch = $item['batch']; $expiry = $item['expiry']; $newStock = $item['stock'] + $item['quantity']; $lotId = null;
        $lot->bind_param('iisssii', $item['id'], $supplierId, $batch, $purchaseDate, $expiry, $item['quantity'], $item['quantity']);
        if (!$lot->execute()) throw new RuntimeException('No fue posible registrar el lote.');
        $lotId = $connection->insert_id;
        $detail->bind_param('iiidi', $purchaseId, $item['id'], $item['quantity'], $item['cost'], $lotId);
        if (!$detail->execute()) throw new RuntimeException('No fue posible registrar el detalle de compra.');
        $update->bind_param('idi', $item['quantity'], $item['cost'], $item['id']);
        if (!$update->execute()) throw new RuntimeException('No fue posible actualizar el stock.');
        $reference = 'COMPRA-' . $purchaseId;
        $movement->bind_param('iiiiiis', $item['id'], $actor['id'], $item['quantity'], $item['stock'], $newStock, $lotId, $reference);
        if (!$movement->execute()) throw new RuntimeException('No fue posible registrar el movimiento.');
    }
    $detail->close(); $update->close(); $lot->close(); $movement->close();
    $connection->commit(); $connection->close();
    jsonResponse(201, ['success' => true, 'purchaseId' => $purchaseId, 'total' => $subtotal, 'message' => 'Compra registrada y stock actualizado.']);
} catch (Throwable $error) {
    $connection->rollback(); $connection->close();
    error_log('LiquorSoft purchase entry failed: ' . $error->getMessage());
    jsonResponse(422, ['message' => $error->getMessage()]);
}
