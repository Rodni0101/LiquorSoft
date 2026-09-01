<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
$connection = databaseConnection();
$user = currentUser($connection);
$connection->close();
if (!$user) {
    jsonResponse(401, ['message' => 'No hay una sesión activa.']);
}
jsonResponse(200, [
    'user' => [
        'id' => (int) $user['id'],
        'name' => trim($user['nombre'] . ' ' . ($user['apellido'] ?? '')),
        'email' => $user['correo'],
        'role' => (string) ($user['rol'] ?? ''),
        'roleId' => (int) ($user['rol_id'] ?? 0),
    ],
]);
