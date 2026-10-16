<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$connection = databaseConnection();
$result = $connection->query(
    "SELECT p.id, p.nombre AS name, COALESCE(c.nombre, 'Sin categoría') AS category,
            COALESCE(p.descripcion, '') AS description, p.precio AS price, p.stock,
            0 AS featured
     FROM productos p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     WHERE p.estado = 1 ORDER BY p.nombre ASC"
);
if (!$result) {
    error_log('LiquorSoft products query failed: ' . $connection->error);
    $connection->close();
    jsonResponse(500, ['message' => 'No fue posible cargar los productos.']);
}
$products = [];
while ($product = $result->fetch_assoc()) {
    $product['id'] = (int) $product['id'];
    $product['price'] = (float) $product['price'];
    $product['stock'] = (int) $product['stock'];
    $product['featured'] = (bool) $product['featured'];
    $product['icon'] = match ($product['category']) {
        'Vinos' => '🍷', 'Cervezas' => '🍺', 'Mixers' => '🫧', default => '🥃',
    };
    $products[] = $product;
}
$connection->close();
jsonResponse(200, ['products' => $products]);
