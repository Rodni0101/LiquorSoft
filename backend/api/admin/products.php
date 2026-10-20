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

    $active = array_key_exists('active', $body)
        ? (int) (bool) $body['active']
        : 1;

    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        jsonResponse(422, [
            'message' => 'El nombre debe tener entre 2 y 150 caracteres.'
        ]);
    }

    if (
        mb_strlen($sku) > 40 ||
        mb_strlen($brand) > 80 ||
        mb_strlen($presentation) > 60 ||
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

    $brandId = resolveBrandId($connection, $brand);
    $providerId = resolveProviderId($connection);

    if ($sku === '') {
        $sku = 'LS-' . strtoupper(bin2hex(random_bytes(5)));
    }

    return [
        'name' => $name,
        'sku' => $sku,
        'brandId' => $brandId,
        'providerId' => $providerId,
        'presentation' => $presentation !== '' ? $presentation : null,
        'description' => $description !== '' ? $description : null,
        'categoryId' => (int) $categoryId,
        'graduation' => $graduation === null ? null : (float) $graduation,
        'purchasePrice' => (float) $purchasePrice,
        'price' => (float) $price,
        'stock' => (int) $stock,
        'minStock' => (int) $minStock,
        'maxStock' => max(100, (int) $stock * 2, (int) $minStock * 3),
        'image' => $image !== '' ? $image : null,
        'active' => $active,
    ];
}

function resolveBrandId(mysqli $connection, string $brand): int
{
    if ($brand !== '') {
        $statement = $connection->prepare('SELECT id FROM marcas WHERE nombre = ? LIMIT 1');
        $statement->bind_param('s', $brand);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($row) {
            return (int) $row['id'];
        }
        $insert = $connection->prepare('INSERT INTO marcas (nombre, estado) VALUES (?, 1)');
        $insert->bind_param('s', $brand);
        if ($insert->execute()) {
            $id = (int) $insert->insert_id;
            $insert->close();
            return $id;
        }
        $insert->close();
        // Otra petición pudo crearla entre la consulta y el insert.
        $retry = $connection->prepare('SELECT id FROM marcas WHERE nombre = ? LIMIT 1');
        $retry->bind_param('s', $brand);
        $retry->execute();
        $row = $retry->get_result()->fetch_assoc();
        $retry->close();
        if ($row) {
            return (int) $row['id'];
        }
        jsonResponse(500, ['message' => 'No fue posible registrar la marca.']);
    }

    $result = $connection->query('SELECT id FROM marcas ORDER BY id LIMIT 1');
    if ($result && ($row = $result->fetch_assoc())) {
        return (int) $row['id'];
    }

    $connection->query("INSERT INTO marcas (nombre, estado) VALUES ('General', 1)");
    return (int) $connection->insert_id;
}

function resolveProviderId(mysqli $connection): int
{
    $result = $connection->query('SELECT id FROM proveedores ORDER BY id LIMIT 1');
    if ($result && ($row = $result->fetch_assoc())) {
        return (int) $row['id'];
    }

    $connection->query("INSERT INTO proveedores (empresa, nit, estado) VALUES ('Proveedor general', 'LS-GENERAL', 1)");
    return (int) $connection->insert_id;
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
            p.categoria_id AS categoryId,
            c.nombre AS category
        FROM productos p
        LEFT JOIN marcas m ON m.id = p.marca_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
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

if ($method === 'DELETE') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        $connection->close();
        jsonResponse(422, ['message' => 'El producto es obligatorio.']);
    }

    $statement = $connection->prepare('UPDATE productos SET estado = 0 WHERE id = ?');
    $statement->bind_param('i', $id);
    if (!$statement->execute()) {
        $statement->close();
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible eliminar el producto.']);
    }
    $affected = $statement->affected_rows;
    $statement->close();
    $connection->close();

    if ($affected < 1) {
        jsonResponse(404, ['message' => 'El producto no existe.']);
    }

    jsonResponse(200, ['message' => 'Producto eliminado correctamente.']);
}

if ($method !== 'POST' && $method !== 'PUT') {
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

if ($method === 'POST') {
    $sql = "
        INSERT INTO productos (
            categoria_id, marca_id, proveedor_id, codigo, nombre, descripcion,
            presentacion, grado_alcohol, stock, stock_minimo, stock_maximo,
            costo, precio, imagen, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $statement = $connection->prepare($sql);

    if (!$statement) {
        $connection->close();
        jsonResponse(500, [
            'message' => 'No fue posible preparar el registro del producto.'
        ]);
    }

    $statement->bind_param(
        'iiissssdiiiddsi',
        $data['categoryId'],
        $data['brandId'],
        $data['providerId'],
        $data['sku'],
        $data['name'],
        $data['description'],
        $data['presentation'],
        $data['graduation'],
        $data['stock'],
        $data['minStock'],
        $data['maxStock'],
        $data['purchasePrice'],
        $data['price'],
        $data['image'],
        $data['active']
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
        'message' => 'Producto registrado correctamente.',
    ]);
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    $connection->close();
    jsonResponse(422, ['message' => 'El producto es obligatorio.']);
}

$sql = "
    UPDATE productos SET
        categoria_id = ?, marca_id = ?, proveedor_id = ?, codigo = ?, nombre = ?,
        descripcion = ?, presentacion = ?, grado_alcohol = ?, stock = ?,
        stock_minimo = ?, stock_maximo = ?, costo = ?, precio = ?, imagen = ?,
        estado = ?
    WHERE id = ?
";

$statement = $connection->prepare($sql);

if (!$statement) {
    $connection->close();
    jsonResponse(500, ['message' => 'No fue posible preparar la actualización.']);
}

$statement->bind_param(
    'iiissssdiiiddsii',
    $data['categoryId'],
    $data['brandId'],
    $data['providerId'],
    $data['sku'],
    $data['name'],
    $data['description'],
    $data['presentation'],
    $data['graduation'],
    $data['stock'],
    $data['minStock'],
    $data['maxStock'],
    $data['purchasePrice'],
    $data['price'],
    $data['image'],
    $data['active'],
    $id
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

    jsonResponse(500, ['message' => 'No fue posible actualizar el producto.']);
}

$affected = $statement->affected_rows;
$statement->close();
$connection->close();

if ($affected < 1) {
    jsonResponse(404, ['message' => 'El producto no existe o no tiene cambios.']);
}

jsonResponse(200, [
    'success' => true,
    'id' => $id,
    'message' => 'Producto actualizado correctamente.',
]);
