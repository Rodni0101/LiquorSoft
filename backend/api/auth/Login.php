<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function loginResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    loginResponse(405, ['message' => 'Método no permitido.']);
}

$body = json_decode(file_get_contents('php://input'), true);
$correo = strtolower(trim((string) ($body['correo'] ?? '')));
$password = (string) ($body['password'] ?? '');
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $password === '') {
    loginResponse(422, ['message' => 'Ingresa un correo y una contraseña válidos.']);
}

mysqli_report(MYSQLI_REPORT_OFF);
$connection = new mysqli(
    liquorsoftEnv('LIQURSOFT_DB_HOST', '127.0.0.1'),
    liquorsoftEnv('LIQURSOFT_DB_USER', 'root'),
    liquorsoftEnv('LIQURSOFT_DB_PASSWORD', ''),
    liquorsoftEnv('LIQURSOFT_DB_NAME', 'liquorsoft')
);
if ($connection->connect_errno) {
    error_log('LiquorSoft database connection failed: ' . $connection->connect_error);
    loginResponse(503, ['message' => 'El servicio no está disponible en este momento.']);
}
$connection->set_charset('utf8mb4');
$statement = $connection->prepare('SELECT u.id, u.nombre, u.apellido, u.password_hash, r.id AS rol_id, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id WHERE u.correo = ? AND COALESCE(u.estado, 1) = 1 LIMIT 1');
$statement->bind_param('s', $correo);
$statement->execute();
$result = $statement->get_result();
$user = $result->fetch_assoc();
$statement->close();
$connection->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    loginResponse(401, ['message' => 'El correo o la contraseña son incorrectos.']);
}

session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['nombre'];
$_SESSION['user_role'] = $user['rol'] ?? '';
loginResponse(200, ['message' => 'Inicio de sesión correcto.', 'user' => ['id' => (int) $user['id'], 'name' => trim($user['nombre'] . ' ' . ($user['apellido'] ?? '')), 'role' => $user['rol'] ?? '', 'roleId' => (int) ($user['rol_id'] ?? 0)]]);
