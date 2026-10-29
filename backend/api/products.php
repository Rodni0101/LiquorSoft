<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$connection = databaseConnection();

// Soporte para consulta de un producto individual por identificador
if (isset($_GET['id'])) {
    $rawId = trim((string) $_GET['id']);
    $id = filter_var($rawId, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        $connection->close();
        jsonResponse(422, ['message' => 'El identificador de producto debe ser un número entero positivo.']);
    }

    $stmt = $connection->prepare(
        "SELECT p.id, p.nombre AS name, COALESCE(c.nombre, 'Sin categoría') AS category,
                COALESCE(p.descripcion, '') AS description, p.precio AS price, p.stock, p.imagen AS image,
                p.destacado AS featured
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.id = ? AND p.estado = 1
         LIMIT 1"
    );
    if (!$stmt) {
        error_log('LiquorSoft single product query prepare failed: ' . $connection->error);
        $connection->close();
        jsonResponse(500, ['message' => 'Error al consultar el producto en el servidor.']);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $product = $res->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $connection->close();
        jsonResponse(404, ['message' => 'El producto solicitado no existe o no se encuentra disponible en este momento.']);
    }

    $product['id'] = (int) $product['id'];
    $product['price'] = (float) $product['price'];
    $product['stock'] = (int) $product['stock'];
    $product['image'] = $product['image'] !== null && trim((string) $product['image']) !== ''
        ? (string) $product['image']
        : null;
    $product['featured'] = (bool) $product['featured'];
    $product['icon'] = productIcon((string) $product['category']);

    // Productos relacionados de la misma categoría
    $related = [];
    $relStmt = $connection->prepare(
        "SELECT p.id, p.nombre AS name, COALESCE(c.nombre, 'Sin categoría') AS category,
                COALESCE(p.descripcion, '') AS description, p.precio AS price, p.stock, p.imagen AS image,
                p.destacado AS featured
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         WHERE p.id != ? AND p.estado = 1 AND COALESCE(c.nombre, '') = ?
         ORDER BY p.nombre ASC
         LIMIT 4"
    );
    if ($relStmt) {
        $relStmt->bind_param('is', $id, $product['category']);
        $relStmt->execute();
        $relRes = $relStmt->get_result();
        while ($rel = $relRes->fetch_assoc()) {
            $rel['id'] = (int) $rel['id'];
            $rel['price'] = (float) $rel['price'];
            $rel['stock'] = (int) $rel['stock'];
            $rel['image'] = $rel['image'] !== null && trim((string) $rel['image']) !== ''
                ? (string) $rel['image']
                : null;
            $rel['featured'] = (bool) $rel['featured'];
            $rel['icon'] = productIcon((string) $rel['category']);
            $related[] = $rel;
        }
        $relStmt->close();
    }

    $connection->close();
    jsonResponse(200, ['product' => $product, 'related' => $related]);
}

// Listado general de productos activos
$result = $connection->query(
    "SELECT p.id, p.nombre AS name, COALESCE(c.nombre, 'Sin categoría') AS category,
            COALESCE(p.descripcion, '') AS description, p.precio AS price, p.stock, p.imagen AS image,
            p.destacado AS featured
     FROM productos p
     LEFT JOIN categorias c ON c.id = p.categoria_id
     WHERE p.estado = 1 ORDER BY p.destacado DESC, p.nombre ASC"
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
    $product['image'] = $product['image'] !== null && trim((string) $product['image']) !== ''
        ? (string) $product['image']
        : null;
    $product['featured'] = (bool) $product['featured'];
    $product['icon'] = productIcon((string) $product['category']);
    $products[] = $product;
}
$connection->close();
jsonResponse(200, ['products' => $products]);
