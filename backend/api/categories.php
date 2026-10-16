<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$connection = databaseConnection();
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    requireRole($connection, ['Administrador']);
    if (!in_array($method, ['POST', 'PATCH'], true)) {
        $connection->close();
        jsonResponse(405, ['message' => 'Método no permitido.']);
    }
    $body = json_decode(file_get_contents('php://input'), true);
    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string) ($body['name'] ?? ''));
    $description = trim((string) ($body['description'] ?? ''));
    $active = array_key_exists('active', $body ?? []) ? (int) (bool) $body['active'] : 1;
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        $connection->close();
        jsonResponse(422, ['message' => 'El nombre de la categoría debe tener entre 2 y 80 caracteres.']);
    }
    if ($method === 'POST') {
        $statement = $connection->prepare('INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, ?)');
        $statement->bind_param('ssi', $name, $description, $active);
    } else {
        if (!$id) { $connection->close(); jsonResponse(422, ['message' => 'La categoría es obligatoria.']); }
        $statement = $connection->prepare('UPDATE categorias SET nombre = ?, descripcion = ?, estado = ? WHERE id = ?');
        $statement->bind_param('ssii', $name, $description, $active, $id);
    }
    if (!$statement || !$statement->execute()) {
        $duplicate = $statement?->errno === 1062;
        $statement?->close(); $connection->close();
        jsonResponse($duplicate ? 409 : 500, ['message' => $duplicate ? 'La categoría ya existe.' : 'No fue posible guardar la categoría.']);
    }
    $savedId = $id ?: $connection->insert_id;
    $statement->close(); $connection->close();
    jsonResponse($method === 'POST' ? 201 : 200, ['success' => true, 'id' => $savedId, 'message' => 'Categoría guardada correctamente.']);
}
$result = $connection->query('SELECT id, nombre AS name FROM categorias WHERE COALESCE(estado, 1) = 1 ORDER BY nombre ASC');
if (!$result) {
    $connection->close();
    jsonResponse(500, ['message' => 'No fue posible cargar las categorías.']);
}
$categories = [];
while ($category = $result->fetch_assoc()) {
    $categories[] = ['id' => (int) $category['id'], 'name' => (string) $category['name']];
}
$connection->close();
jsonResponse(200, ['categories' => $categories]);
