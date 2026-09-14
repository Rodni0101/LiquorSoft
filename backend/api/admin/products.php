<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

$connection = databaseConnection();
requireRole($connection, ['Administrador']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $connection->query(
        "SELECT p.id, p.nombre AS name, COALESCE(c.nombre, 'Sin categoría') AS category,
                p.categoria_id AS categoryId, COALESCE(p.descripcion, '') AS description,
                p.precio AS price, p.stock, COALESCE(p.stock_minimo, 5) AS minStock,
                COALESCE(p.destacado, 0) AS featured, COALESCE(p.estado, 1) AS active
         FROM productos p
         LEFT JOIN categorias c ON c.id = p.categoria_id
         ORDER BY p.nombre ASC"
    );
    if (!$result) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar los productos.']);
    }
    $products = [];
    while ($product = $result->fetch_assoc()) {
        $category = (string) $product['category'];
        $products[] = [
            'id' => (int) $product['id'],
            'name' => (string) $product['name'],
            'category' => $category,
            'categoryId' => $product['categoryId'] !== null ? (int) $product['categoryId'] : null,
            'description' => (string) $product['description'],
            'price' => (float) $product['price'],
            'stock' => (int) $product['stock'],
            'minStock' => (int) $product['minStock'],
            'featured' => (bool) $product['featured'],
            'active' => (bool) $product['active'],
            'icon' => productIcon($category),
        ];
    }
    $connection->close();
    jsonResponse(200, ['products' => $products]);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $connection->close();
    jsonResponse(400, ['message' => 'La solicitud no tiene un formato válido.']);
}

function validateProductPayload(array $body, mysqli $connection, bool $partial): array
{
    $name = trim((string) ($body['name'] ?? $body['nombre'] ?? ''));
    $description = trim((string) ($body['description'] ?? $body['descripcion'] ?? ''));
    $price = filter_var($body['price'] ?? $body['precio'] ?? null, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($body['stock'] ?? null, FILTER_VALIDATE_INT);
    $minStock = filter_var($body['minStock'] ?? $body['stock_minimo'] ?? 5, FILTER_VALIDATE_INT);
    $categoryId = filter_var($body['categoryId'] ?? $body['categoria_id'] ?? null, FILTER_VALIDATE_INT);
    $featured = !empty($body['featured'] ?? $body['destacado']);
    $active = array_key_exists('active', $body) ? (bool) $body['active'] : (array_key_exists('estado', $body) ? (bool) $body['estado'] : true);

    if (!$partial && (mb_strlen($name) < 2 || mb_strlen($name) > 150)) {
        jsonResponse(422, ['message' => 'El nombre del producto debe tener entre 2 y 150 caracteres.']);
    }
    if ($partial && $name !== '' && (mb_strlen($name) < 2 || mb_strlen($name) > 150)) {
        jsonResponse(422, ['message' => 'El nombre del producto debe tener entre 2 y 150 caracteres.']);
    }
    if ($price !== false && $price < 0) {
        jsonResponse(422, ['message' => 'El precio no puede ser negativo.']);
    }
    if ($stock !== false && $stock < 0) {
        jsonResponse(422, ['message' => 'El stock no puede ser negativo.']);
    }
    if ($minStock === false || $minStock < 0) {
        $minStock = 5;
    }
    if ($categoryId) {
        $check = $connection->prepare('SELECT id FROM categorias WHERE id = ? LIMIT 1');
        $check->bind_param('i', $categoryId);
        $check->execute();
        $exists = (bool) $check->get_result()->fetch_assoc();
        $check->close();
        if (!$exists) {
            jsonResponse(422, ['message' => 'La categoría seleccionada no existe.']);
        }
    }
    return [
        'name' => $name,
        'description' => mb_substr($description, 0, 500),
        'price' => $price === false ? null : $price,
        'stock' => $stock === false ? null : $stock,
        'minStock' => $minStock,
        'categoryId' => $categoryId ?: null,
        'featured' => $featured ? 1 : 0,
        'active' => $active ? 1 : 0,
    ];
}

if ($method === 'POST') {
    $data = validateProductPayload($body, $connection, false);
    if ($data['price'] === null || $data['stock'] === null) {
        $connection->close();
        jsonResponse(422, ['message' => 'Precio y stock son obligatorios.']);
    }
    $statement = $connection->prepare(
        'INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, destacado, estado, categoria_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$statement) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible crear el producto.']);
    }
    $statement->bind_param(
        'ssdiiiii',
        $data['name'],
        $data['description'],
        $data['price'],
        $data['stock'],
        $data['minStock'],
        $data['featured'],
        $data['active'],
        $data['categoryId']
    );
    if (!$statement->execute()) {
        $statement->close();
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible crear el producto.']);
    }
    $id = $connection->insert_id;
    $statement->close();
    $connection->close();
    jsonResponse(201, ['success' => true, 'id' => $id, 'message' => 'Producto creado correctamente.']);
}

if ($method !== 'PATCH' && $method !== 'PUT') {
    $connection->close();
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    $connection->close();
    jsonResponse(422, ['message' => 'El identificador del producto es obligatorio.']);
}
$data = validateProductPayload($body, $connection, true);
$current = $connection->prepare('SELECT nombre, descripcion, precio, stock, stock_minimo, destacado, estado, categoria_id FROM productos WHERE id = ? LIMIT 1');
$current->bind_param('i', $id);
$current->execute();
$row = $current->get_result()->fetch_assoc();
$current->close();
if (!$row) {
    $connection->close();
    jsonResponse(404, ['message' => 'El producto no existe.']);
}

$name = $data['name'] !== '' ? $data['name'] : $row['nombre'];
$description = array_key_exists('description', $body) || array_key_exists('descripcion', $body) ? $data['description'] : $row['descripcion'];
$price = $data['price'] !== null ? $data['price'] : (float) $row['precio'];
$stock = $data['stock'] !== null ? $data['stock'] : (int) $row['stock'];
$minStock = $data['minStock'];
$featured = array_key_exists('featured', $body) || array_key_exists('destacado', $body) ? $data['featured'] : (int) $row['destacado'];
$active = array_key_exists('active', $body) || array_key_exists('estado', $body) ? $data['active'] : (int) $row['estado'];
$categoryId = array_key_exists('categoryId', $body) || array_key_exists('categoria_id', $body) ? $data['categoryId'] : ($row['categoria_id'] !== null ? (int) $row['categoria_id'] : null);

$statement = $connection->prepare(
    'UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, stock = ?, stock_minimo = ?, destacado = ?, estado = ?, categoria_id = ? WHERE id = ?'
);
$statement->bind_param('ssdiiiiii', $name, $description, $price, $stock, $minStock, $featured, $active, $categoryId, $id);
$ok = $statement->execute();
$statement->close();
$connection->close();
if (!$ok) {
    jsonResponse(500, ['message' => 'No fue posible actualizar el producto.']);
}
jsonResponse(200, ['success' => true, 'message' => 'Producto actualizado correctamente.']);
