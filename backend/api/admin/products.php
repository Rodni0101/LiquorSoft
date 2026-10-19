<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$connection = databaseConnection();
requireRole($connection, ['Administrador']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function productPayload(array $body, mysqli $connection): array
{
    $name = trim((string) ($body['name'] ?? $body['nombre'] ?? ''));
    $sku = trim((string) ($body['sku'] ?? ''));
    $brand = trim((string) ($body['brand'] ?? $body['marca'] ?? ''));
    $presentation = trim((string) ($body['presentation'] ?? $body['presentacion'] ?? ''));
    $description = trim((string) ($body['description'] ?? $body['descripcion'] ?? ''));
    $image = trim((string) ($body['image'] ?? $body['imagen'] ?? ''));

    $categoryId = filter_var(
        $body['categoryId'] ?? $body['categoria_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $price = filter_var(
        $body['price'] ?? $body['precio'] ?? null,
        FILTER_VALIDATE_FLOAT
    );

    $purchasePrice = filter_var(
        $body['purchasePrice'] ?? $body['precio_compra'] ?? 0,
        FILTER_VALIDATE_FLOAT
    );

    $stock = filter_var(
        $body['stock'] ?? null,
        FILTER_VALIDATE_INT
    );

    $minStock = filter_var(
        $body['minStock'] ?? $body['stock_minimo'] ?? 5,
        FILTER_VALIDATE_INT
    );

    $rawGraduation = $body['alcoholicGraduation']
        ?? $body['graduacion_alcoholica']
        ?? '';

    $graduation = $rawGraduation === '' || $rawGraduation === null
        ? null
        : filter_var($rawGraduation, FILTER_VALIDATE_FLOAT);

    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        jsonResponse(422, [
            'message' => 'El nombre debe tener entre 2 y 150 caracteres.'
        ]);
    }

    if (
        mb_strlen($sku) > 60 ||
        mb_strlen($brand) > 100 ||
        mb_strlen($presentation) > 100 ||
        mb_strlen($image) > 255 ||
        mb_strlen($description) > 500
    ) {
        jsonResponse(422, [
            'message' => 'Uno de los campos supera la longitud permitida.'
        ]);
    }

    if ($price === false || $price === null || $price < 0) {
        jsonResponse(422, [
            'message' => 'Ingresa un precio de venta válido.'
        ]);
    }

    if ($purchasePrice === false || $purchasePrice === null || $purchasePrice < 0) {
        jsonResponse(422, [
            'message' => 'Ingresa un precio de compra válido.'
        ]);
    }

    if (
        $stock === false || $stock === null || $stock < 0 ||
        $minStock === false || $minStock === null || $minStock < 0
    ) {
        jsonResponse(422, [
            'message' => 'El stock y el stock mínimo deben ser enteros no negativos.'
        ]);
    }

    if (
        $graduation !== null &&
        ($graduation === false || $graduation < 0 || $graduation > 100)
    ) {
        jsonResponse(422, [
            'message' => 'La graduación debe estar entre 0 y 100 %.'
        ]);
    }

    if ($categoryId === false || $categoryId === null || $categoryId < 1) {
        jsonResponse(422, [
            'message' => 'Selecciona una categoría válida.'
        ]);
    }

    $check = $connection->prepare(
        'SELECT id FROM categorias WHERE id = ? LIMIT 1'
    );

    if (!$check) {
        jsonResponse(500, [
            'message' => 'No fue posible verificar la categoría.'
        ]);
    }

    $check->bind_param('i', $categoryId);
    $check->execute();

    $categoryExists = (bool) $check->get_result()->fetch_assoc();
    $check->close();

    if (!$categoryExists) {
        jsonResponse(422, [
            'message' => 'La categoría seleccionada no existe.'
        ]);
    }

    return [
        'name' => $name,
        'sku' => $sku !== '' ? $sku : null,
        'brand' => $brand !== '' ? $brand : null,
        'presentation' => $presentation !== '' ? $presentation : null,
        'description' => $description !== '' ? $description : null,
        'categoryId' => (int) $categoryId,
        'graduation' => $graduation === null ? null : (float) $graduation,
        'purchasePrice' => (float) $purchasePrice,
        'price' => (float) $price,
        'stock' => (int) $stock,
        'minStock' => (int) $minStock,
        'image' => $image !== '' ? $image : null,
        'featured' => !empty($body['featured'] ?? $body['destacado'] ?? false) ? 1 : 0,
        'active' => array_key_exists('active', $body)
            ? (int) (bool) $body['active']
            : 1,
    ];
}

/**
 * GET: devuelve los productos para el panel de administración.
 */
if ($method === 'GET') {
    $sql = "
        SELECT
            p.id,
            p.nombre AS name,
            p.codigo AS sku,
            m.nombre AS brand,
            p.presentacion AS presentation,
            p.grado_alcohol AS alcoholicGraduation,
            p.costo AS purchasePrice,
            p.precio AS price,
            p.stock,
            p.stock_minimo AS minStock,
            p.descripcion AS description,
            p.imagen AS image,
            0 AS featured,
            p.estado AS active,
            NULL AS categoryId,
            NULL AS category
        FROM productos p
        LEFT JOIN marcas m ON m.id = p.marca_id
        ORDER BY p.nombre ASC
    ";

    $result = $connection->query($sql);

    if (!$result) {
        $connection->close();

        jsonResponse(500, [
            'message' => 'No fue posible cargar los productos.'
        ]);
    }

    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'sku' => $row['sku'],
            'brand' => $row['brand'],
            'presentation' => $row['presentation'],
            'alcoholicGraduation' => $row['alcoholicGraduation'] !== null
                ? (float) $row['alcoholicGraduation']
                : null,
            'purchasePrice' => (float) $row['purchasePrice'],
            'price' => (float) $row['price'],
            'stock' => (int) $row['stock'],
            'minStock' => (int) $row['minStock'],
            'description' => (string) ($row['description'] ?? ''),
            'image' => $row['image'],
            'featured' => (bool) $row['featured'],
            'active' => (bool) $row['active'],
            'categoryId' => $row['categoryId'] !== null
                ? (int) $row['categoryId']
                : null,
            'category' => (string) $row['category'],
        ];
    }

    $connection->close();

    jsonResponse(200, [
        'products' => $products
    ]);
}

/**
 * Solo se aceptan solicitudes POST para registrar productos.
 */
if ($method !== 'POST') {
    $connection->close();

    jsonResponse(405, [
        'message' => 'Método no permitido.'
    ]);
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (!is_array($body)) {
    $connection->close();

    jsonResponse(400, [
        'message' => 'La solicitud no tiene un formato JSON válido.'
    ]);
}

$data = productPayload($body, $connection);

$sql = "
    INSERT INTO productos (
        sku,
        nombre,
        descripcion,
        marca,
        presentacion,
        graduacion_alcoholica,
        precio_compra,
        precio,
        stock,
        stock_minimo,
        destacado,
        estado,
        categoria_id,
        imagen
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$statement = $connection->prepare($sql);

if (!$statement) {
    $connection->close();

    jsonResponse(500, [
        'message' => 'No fue posible preparar el registro del producto.'
    ]);
}

/*
 * 14 parámetros:
 * 5 textos, 3 decimales, 5 enteros y 1 texto.
 */
$statement->bind_param(
    'sssssdddiiiiis',
    $data['sku'],
    $data['name'],
    $data['description'],
    $data['brand'],
    $data['presentation'],
    $data['graduation'],
    $data['purchasePrice'],
    $data['price'],
    $data['stock'],
    $data['minStock'],
    $data['featured'],
    $data['active'],
    $data['categoryId'],
    $data['image']
);

if (!$statement->execute()) {
    $errno = $statement->errno;

    $statement->close();
    $connection->close();

    if ($errno === 1062) {
        jsonResponse(409, [
            'message' => 'Ese SKU ya está registrado. Utiliza otra referencia.'
        ]);
    }

    jsonResponse(500, [
        'message' => 'No fue posible guardar el producto en la base de datos.'
    ]);
}

$id = $connection->insert_id;

$statement->close();
$connection->close();

jsonResponse(201, [
    'success' => true,
    'id' => $id,
    'message' => 'Producto registrado correctamente.'
]);
