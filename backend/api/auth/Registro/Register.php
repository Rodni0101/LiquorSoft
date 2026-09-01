<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['message' => 'Método no permitido.']);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    respond(400, ['message' => 'La solicitud no tiene un formato válido.']);
}

$nombre = trim((string) ($body['nombre'] ?? ''));
$apellido = trim((string) ($body['apellido'] ?? ''));
$correo = strtolower(trim((string) ($body['correo'] ?? '')));
$password = (string) ($body['password'] ?? '');

if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
    respond(422, ['message' => 'El nombre debe tener entre 2 y 100 caracteres.']);
}
if (mb_strlen($apellido) < 2 || mb_strlen($apellido) > 100) {
    respond(422, ['message' => 'El apellido debe tener entre 2 y 100 caracteres.']);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
    respond(422, ['message' => 'El correo electrónico no es válido.']);
}
if (strlen($password) < 8 || strlen($password) > 72) {
    respond(422, ['message' => 'La contraseña debe tener entre 8 y 72 caracteres.']);
}

// Configure these values in the server environment; no credentials belong in source control.
$host = liquorsoftEnv('LIQURSOFT_DB_HOST', '127.0.0.1');
$user = liquorsoftEnv('LIQURSOFT_DB_USER', 'root');
$dbPassword = liquorsoftEnv('LIQURSOFT_DB_PASSWORD', '');
$database = liquorsoftEnv('LIQURSOFT_DB_NAME', 'liquorsoft');

mysqli_report(MYSQLI_REPORT_OFF);
$connection = new mysqli($host, $user, $dbPassword, $database);
if ($connection->connect_errno) {
    error_log('LiquorSoft database connection failed: ' . $connection->connect_error);
    respond(503, ['message' => 'El servicio no está disponible en este momento.']);
}
$connection->set_charset('utf8mb4');

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$roleResult = $connection->query('SELECT id FROM roles ORDER BY id ASC LIMIT 1');
if (!$roleResult || !$roleResult->num_rows) {
    error_log('LiquorSoft register failed: no existe un rol disponible en la tabla roles.');
    $connection->close();
    respond(500, ['message' => 'No existe un rol disponible para crear la cuenta.']);
}
$rolId = (int) $roleResult->fetch_assoc()['id'];
$usernameBase = strtolower((string) strstr($correo, '@', true));
$usernameBase = preg_replace('/[^a-z0-9._-]/', '', $usernameBase) ?: 'usuario';
$usuario = substr($usernameBase, 0, 40) . '_' . bin2hex(random_bytes(4));

$statement = $connection->prepare(
    'INSERT INTO usuarios
      (rol_id, nombre, apellido, correo, password_hash, usuario, password, estado)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
);
if (!$statement) {
    error_log('LiquorSoft register prepare failed: ' . $connection->error);
    $connection->close();
    respond(500, ['message' => 'No fue posible procesar el registro.']);
}

$statement->bind_param('issssss', $rolId, $nombre, $apellido, $correo, $passwordHash, $usuario, $passwordHash);
if (!$statement->execute()) {
    error_log('LiquorSoft register execute failed: ' . $statement->error);
    $duplicate = $statement->errno === 1062;
    $statement->close();
    $connection->close();
    respond($duplicate ? 409 : 500, [
        'message' => $duplicate ? 'Ya existe una cuenta con ese correo.' : 'No fue posible crear la cuenta.',
    ]);
}

$statement->close();
$connection->close();
respond(201, ['message' => 'Cuenta creada correctamente.']);
