<?php
declare(strict_types=1);

function databaseConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = new mysqli(
        liquorsoftEnv('LIQURSOFT_DB_HOST', '127.0.0.1'),
        liquorsoftEnv('LIQURSOFT_DB_USER', 'root'),
        liquorsoftEnv('LIQURSOFT_DB_PASSWORD', ''),
        liquorsoftEnv('LIQURSOFT_DB_NAME', 'liquorsoft')
    );
    if ($connection->connect_errno) {
        error_log('LiquorSoft database connection failed: ' . $connection->connect_error);
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => 'El servicio no está disponible en este momento.']);
        exit;
    }
    $connection->set_charset('utf8mb4');
    return $connection;
}

function liquorsoftEnv(string $key, string $fallback): string
{
    $environmentValue = getenv($key);
    if ($environmentValue !== false) {
        return $environmentValue;
    }
    static $fileValues;
    if ($fileValues === null) {
        $envFile = dirname(__DIR__, 2) . '/.env';
        $fileValues = is_file($envFile) ? (parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: []) : [];
    }
    return isset($fileValues[$key]) ? trim((string) $fileValues[$key], " \t\n\r\0\x0B\"'") : $fallback;
}

function jsonResponse(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuthenticatedUser(): void
{
    session_start();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(401, ['message' => 'Debes iniciar sesión para acceder.']);
    }
}

function currentUser(mysqli $connection): ?array
{
    session_start();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $statement = $connection->prepare(
        'SELECT u.id, u.nombre, u.apellido, u.correo, r.id AS rol_id, r.nombre AS rol
         FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id
         WHERE u.id = ? AND COALESCE(u.estado, 1) = 1 LIMIT 1'
    );
    if (!$statement) {
        return null;
    }
    $userId = (int) $_SESSION['user_id'];
    $statement->bind_param('i', $userId);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();
    if ($user) {
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_role'] = $user['rol'] ?? '';
    }
    return $user;
}

function requireRole(mysqli $connection, array $allowedRoles): array
{
    $user = currentUser($connection);
    if (!$user) {
        jsonResponse(401, ['message' => 'Debes iniciar sesión para acceder.']);
    }
    $role = mb_strtolower(trim((string) ($user['rol'] ?? '')));
    $allowed = array_map(static fn (string $value): string => mb_strtolower(trim($value)), $allowedRoles);
    if (!in_array($role, $allowed, true)) {
        jsonResponse(403, ['message' => 'No tienes permisos para acceder a esta sección.']);
    }
    return $user;
}
