<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
$connection = databaseConnection();
requireRole($connection, ['Administrador']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $connection->query('SELECT id, nombre_empresa AS name, identificacion, contacto, telefono, correo, direccion, estado AS active FROM proveedores ORDER BY nombre_empresa');
    if (!$result) { $connection->close(); jsonResponse(500, ['message' => 'No fue posible cargar los proveedores.']); }
    $providers = [];
    while ($row = $result->fetch_assoc()) {
        $providers[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'identification' => (string) ($row['identificacion'] ?? ''), 'contact' => (string) ($row['contacto'] ?? ''), 'phone' => (string) ($row['telefono'] ?? ''), 'email' => (string) ($row['correo'] ?? ''), 'address' => (string) ($row['direccion'] ?? ''), 'active' => (bool) $row['active']];
    }
    $connection->close(); jsonResponse(200, ['providers' => $providers]);
}
if (!in_array($method, ['POST', 'PATCH'], true)) { $connection->close(); jsonResponse(405, ['message' => 'Método no permitido.']); }
$body = json_decode(file_get_contents('php://input'), true);
$id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
$name = trim((string) ($body['name'] ?? ''));
$identification = trim((string) ($body['identification'] ?? ''));
$contact = trim((string) ($body['contact'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$email = strtolower(trim((string) ($body['email'] ?? '')));
$address = trim((string) ($body['address'] ?? ''));
$active = array_key_exists('active', $body ?? []) ? (int) (bool) $body['active'] : 1;
if (mb_strlen($name) < 2 || mb_strlen($name) > 150 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) { $connection->close(); jsonResponse(422, ['message' => 'Nombre o correo del proveedor no válido.']); }
if ($method === 'POST') {
    $statement = $connection->prepare('INSERT INTO proveedores (nombre_empresa, identificacion, contacto, telefono, correo, direccion, estado) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $statement->bind_param('ssssssi', $name, $identification, $contact, $phone, $email, $address, $active);
} else {
    if (!$id) { $connection->close(); jsonResponse(422, ['message' => 'El proveedor es obligatorio.']); }
    $statement = $connection->prepare('UPDATE proveedores SET nombre_empresa = ?, identificacion = ?, contacto = ?, telefono = ?, correo = ?, direccion = ?, estado = ? WHERE id = ?');
    $statement->bind_param('ssssssii', $name, $identification, $contact, $phone, $email, $address, $active, $id);
}
if (!$statement || !$statement->execute()) { $duplicate = $statement?->errno === 1062; $statement?->close(); $connection->close(); jsonResponse($duplicate ? 409 : 500, ['message' => $duplicate ? 'La identificación ya está registrada.' : 'No fue posible guardar el proveedor.']); }
$savedId = $id ?: $connection->insert_id; $statement->close(); $connection->close();
jsonResponse($method === 'POST' ? 201 : 200, ['success' => true, 'id' => $savedId, 'message' => 'Proveedor guardado correctamente.']);
