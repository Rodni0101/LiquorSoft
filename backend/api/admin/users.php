<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

$connection = databaseConnection();
$admin = requireRole($connection, ['Administrador']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usersResult = $connection->query(
        'SELECT u.id, u.nombre, u.apellido, u.correo, u.estado, r.id AS rol_id, r.nombre AS rol
         FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id ORDER BY u.nombre, u.apellido'
    );
    $rolesResult = $connection->query('SELECT id, nombre FROM roles ORDER BY id');
    if (!$usersResult || !$rolesResult) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar los usuarios.']);
    }
    $users = [];
    while ($user = $usersResult->fetch_assoc()) {
        $users[] = [
            'id' => (int) $user['id'],
            'name' => trim($user['nombre'] . ' ' . ($user['apellido'] ?? '')),
            'email' => $user['correo'],
            'role' => (string) ($user['rol'] ?? ''),
            'roleId' => (int) ($user['rol_id'] ?? 0),
            'active' => (bool) ($user['estado'] ?? 1),
        ];
    }
    $roles = [];
    while ($role = $rolesResult->fetch_assoc()) {
        $roles[] = ['id' => (int) $role['id'], 'name' => (string) $role['nombre']];
    }
    $connection->close();
    jsonResponse(200, ['users' => $users, 'roles' => $roles]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $connection->close();
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$body = json_decode(file_get_contents('php://input'), true);
$userId = (int) ($body['userId'] ?? 0);
$roleId = (int) ($body['roleId'] ?? 0);
if ($userId < 1 || $roleId < 1) {
    $connection->close();
    jsonResponse(422, ['message' => 'Usuario y rol son obligatorios.']);
}
if ($userId === (int) $admin['id']) {
    $connection->close();
    jsonResponse(422, ['message' => 'No puedes cambiar el rol de tu propia cuenta.']);
}

$roleStatement = $connection->prepare('SELECT id, nombre FROM roles WHERE id = ? LIMIT 1');
$roleStatement->bind_param('i', $roleId);
$roleStatement->execute();
$role = $roleStatement->get_result()->fetch_assoc();
$roleStatement->close();
if (!$role) {
    $connection->close();
    jsonResponse(404, ['message' => 'El rol seleccionado no existe.']);
}

$statement = $connection->prepare('UPDATE usuarios SET rol_id = ? WHERE id = ? AND COALESCE(estado, 1) = 1');
$statement->bind_param('ii', $roleId, $userId);
$statement->execute();
$changed = $statement->affected_rows > 0;
$statement->close();
$connection->close();
if (!$changed) {
    jsonResponse(404, ['message' => 'El usuario no existe o está inactivo.']);
}
jsonResponse(200, ['message' => 'Rol actualizado correctamente.', 'role' => ['id' => $roleId, 'name' => $role['nombre']]]);
