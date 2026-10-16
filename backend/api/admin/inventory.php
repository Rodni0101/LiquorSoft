<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $connection->query(
        "SELECT p.id, p.nombre AS name, COALESCE(c.nombre, 'Sin categoría') AS category,
                p.precio AS price, p.stock, COALESCE(p.stock_minimo, 5) AS minStock,
                COALESCE(p.estado, 1) AS active
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.estado = 1
         ORDER BY (p.stock <= COALESCE(p.stock_minimo, 5)) DESC, p.stock ASC, p.nombre ASC"
    );
    if (!$result) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar el inventario.']);
    }
    $items = [];
    $lowStock = 0;
    $outOfStock = 0;
    while ($row = $result->fetch_assoc()) {
        $stock = (int) $row['stock'];
        $minStock = (int) $row['minStock'];
        $status = $stock === 0 ? 'agotado' : ($stock <= $minStock ? 'bajo' : 'ok');
        if ($status === 'agotado') {
            $outOfStock++;
        } elseif ($status === 'bajo') {
            $lowStock++;
        }
        $items[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'category' => (string) $row['category'],
            'price' => (float) $row['price'],
            'stock' => $stock,
            'minStock' => $minStock,
            'status' => $status,
            'icon' => productIcon((string) $row['category']),
        ];
    }

    $movements = [];
    $hasMovements = $connection->query("SHOW TABLES LIKE 'movimientos_inventario'");
    if ($hasMovements && $hasMovements->num_rows) {
        $history = $connection->query(
            "SELECT m.id, m.cantidad AS quantity, m.motivo AS reason, m.creado_en AS createdAt,
                    p.nombre AS productName, CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) AS userName
             FROM movimientos_inventario m
             INNER JOIN productos p ON p.id = m.producto_id
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             ORDER BY m.creado_en DESC
             LIMIT 20"
        );
        if ($history) {
            while ($row = $history->fetch_assoc()) {
                $movements[] = [
                    'id' => (int) $row['id'],
                    'quantity' => (int) $row['quantity'],
                    'reason' => (string) $row['reason'],
                    'createdAt' => (string) $row['createdAt'],
                    'productName' => (string) $row['productName'],
                    'userName' => trim((string) $row['userName']),
                ];
            }
        }
    }
    $connection->close();
    jsonResponse(200, [
        'items' => $items,
        'alerts' => ['lowStock' => $lowStock, 'outOfStock' => $outOfStock],
        'movements' => $movements,
    ]);
}

if ($method !== 'POST') {
    $connection->close();
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$body = json_decode(file_get_contents('php://input'), true);
$productId = filter_var($body['productId'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($body['quantity'] ?? null, FILTER_VALIDATE_INT);
$reason = trim((string) ($body['reason'] ?? 'Ajuste de inventario'));
if (!$productId || $quantity === false || $quantity === 0) {
    $connection->close();
    jsonResponse(422, ['message' => 'Indica un producto y una cantidad distinta de cero.']);
}
if (mb_strlen($reason) < 3 || mb_strlen($reason) > 200) {
    $connection->close();
    jsonResponse(422, ['message' => 'El motivo debe tener entre 3 y 200 caracteres.']);
}

try {
    $connection->begin_transaction();
    $statement = $connection->prepare('SELECT id, stock FROM productos WHERE id = ? AND estado = 1 FOR UPDATE');
    $statement->bind_param('i', $productId);
    $statement->execute();
    $product = $statement->get_result()->fetch_assoc();
    $statement->close();
    if (!$product) {
        throw new RuntimeException('El producto no está disponible.');
    }
    $newStock = (int) $product['stock'] + $quantity;
    if ($newStock < 0) {
        throw new RuntimeException('El ajuste dejaría el stock en negativo.');
    }
    $update = $connection->prepare('UPDATE productos SET stock = ? WHERE id = ?');
    $update->bind_param('ii', $newStock, $productId);
    if (!$update->execute()) {
        throw new RuntimeException('No fue posible actualizar el stock.');
    }
    $update->close();

    $hasMovements = $connection->query("SHOW TABLES LIKE 'movimientos_inventario'");
    if ($hasMovements && $hasMovements->num_rows) {
        $userId = (int) $actor['id'];
        $movementType = $quantity > 0 ? 'ENTRADA' : 'SALIDA';
        $move = $connection->prepare(
            'INSERT INTO movimientos_inventario
             (producto_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $previousStock = (int) $product['stock'];
        $move->bind_param('iisiiis', $productId, $userId, $movementType, $quantity, $previousStock, $newStock, $reason);
        if (!$move->execute()) {
            throw new RuntimeException('No fue posible registrar el movimiento de inventario.');
        }
        $move->close();
    }
    $connection->commit();
    $connection->close();
    jsonResponse(200, ['success' => true, 'stock' => $newStock, 'message' => 'Inventario actualizado.']);
} catch (Throwable $error) {
    $connection->rollback();
    $connection->close();
    jsonResponse(422, ['message' => $error->getMessage()]);
}
