<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$connection = databaseConnection();
$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'], true)) {
    $connection->close();
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

if ($method === 'GET') {
    // El panel solicita ?all=1 para gestionar también las categorías inactivas.
    $includeInactive = isset($_GET['all']) && (string) $_GET['all'] === '1';
    if ($includeInactive) {
        $user = currentUser($connection);
        if (!$user || !in_array('categorias', userPermissions($user), true)) {
            $connection->close();
            jsonResponse(403, ['message' => 'No tienes permisos para gestionar las categorías.']);
        }
        $sql = 'SELECT id, nombre AS name, COALESCE(descripcion, \'\') AS description, COALESCE(estado, 1) AS active
                FROM categorias ORDER BY COALESCE(estado, 1) DESC, nombre ASC';
    } else {
        $sql = 'SELECT c.id, c.nombre AS name, \'\' AS description, 1 AS active
                FROM categorias c
                WHERE COALESCE(c.estado, 1) = 1
                  AND EXISTS (
                      SELECT 1 FROM productos p
                      WHERE p.categoria_id = c.id AND COALESCE(p.estado, 1) = 1
                  )
                ORDER BY c.nombre ASC';
    }

    $result = $connection->query($sql);
    if (!$result) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar las categorías.']);
    }
    $categories = [];
    while ($category = $result->fetch_assoc()) {
        $categories[] = [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
            'description' => (string) ($category['description'] ?? ''),
            'active' => (bool) $category['active'],
        ];
    }
    $connection->close();
    jsonResponse(200, ['categories' => $categories]);
}

requirePermission($connection, 'categorias');
requireCsrfToken();

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);
if (!is_array($body)) {
    $connection->close();
    jsonResponse(400, ['message' => 'La solicitud no tiene un formato JSON válido.']);
}

$id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);

if ($method === 'DELETE') {
    if (!$id) {
        $connection->close();
        jsonResponse(422, ['message' => 'La categoría es obligatoria.']);
    }
    $statement = $connection->prepare('UPDATE categorias SET estado = 0 WHERE id = ?');
    if (!$statement) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible preparar la desactivación.']);
    }
    $statement->bind_param('i', $id);
    if (!$statement->execute()) {
        $statement->close();
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible desactivar la categoría.']);
    }
    $statement->close();
    $connection->close();
    jsonResponse(200, ['success' => true, 'id' => $id, 'message' => 'Categoría desactivada correctamente.']);
}

$name = trim((string) ($body['name'] ?? ''));
$description = trim((string) ($body['description'] ?? ''));
$active = array_key_exists('active', $body) ? (int) (bool) $body['active'] : 1;

if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
    $connection->close();
    jsonResponse(422, ['message' => 'El nombre de la categoría debe tener entre 2 y 80 caracteres.']);
}

if ($method === 'POST') {
    $statement = $connection->prepare('INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, ?)');
    if (!$statement) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible preparar el registro.']);
    }
    $statement->bind_param('ssi', $name, $description, $active);
} else {
    if (!$id) {
        $connection->close();
        jsonResponse(422, ['message' => 'La categoría es obligatoria.']);
    }
    $statement = $connection->prepare('UPDATE categorias SET nombre = ?, descripcion = ?, estado = ? WHERE id = ?');
    if (!$statement) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible preparar la actualización.']);
    }
    $statement->bind_param('ssii', $name, $description, $active, $id);
}

if (!$statement->execute()) {
    $duplicate = $statement->errno === 1062;
    $statement->close();
    $connection->close();
    jsonResponse($duplicate ? 409 : 500, [
        'message' => $duplicate ? 'La categoría ya existe.' : 'No fue posible guardar la categoría.',
    ]);
}

$savedId = $method === 'PATCH' ? $id : $connection->insert_id;
$statement->close();
$connection->close();
jsonResponse($method === 'POST' ? 201 : 200, [
    'success' => true,
    'id' => (int) $savedId,
    'message' => 'Categoría guardada correctamente.',
]);
