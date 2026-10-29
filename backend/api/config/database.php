<?php
declare(strict_types=1);

function databaseConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = @new mysqli(
        liquorsoftEnv('LIQURSOFT_DB_HOST', '127.0.0.1'),
        liquorsoftEnv('LIQURSOFT_DB_USER', 'root'),
        liquorsoftEnv('LIQURSOFT_DB_PASSWORD', ''),
        liquorsoftEnv('LIQURSOFT_DB_NAME', 'liquorsoft')
    );
    if ($connection->connect_errno) {
        error_log('LiquorSoft database connection failed (code ' . $connection->connect_errno . ').');
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
        if (!is_file($envFile)) {
            $rootEnv = dirname(__DIR__, 3) . '/.env';
            if (is_file($rootEnv)) {
                $envFile = $rootEnv;
            }
        }
        $fileValues = [];
        if (is_file($envFile) && is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
                $separator = strpos($line, '=');
                if ($separator === false) continue;
                $name = trim(substr($line, 0, $separator));
                if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) continue;
                $value = trim(substr($line, $separator + 1));
                if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
                    $value = substr($value, 1, -1);
                }
                $fileValues[$name] = $value;
            }
        }
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
    liquorsoftSessionStart();
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(401, ['message' => 'Debes iniciar sesión para acceder.']);
    }
}

function currentUser(mysqli $connection): ?array
{
    liquorsoftSessionStart();
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

function liquorsoftSessionStart(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $configuredPath = liquorsoftEnv('LIQUORSOFT_SESSION_SAVE_PATH', '');
        if ($configuredPath !== '') {
            if (!is_dir($configuredPath)) @mkdir($configuredPath, 0700, true);
            if (is_dir($configuredPath) && is_writable($configuredPath)) session_save_path($configuredPath);
        } elseif (!is_writable(session_save_path())) {
            $fallbackPath = sys_get_temp_dir() . '/liquorsoft-sessions';
            if (!is_dir($fallbackPath)) @mkdir($fallbackPath, 0700, true);
            if (is_dir($fallbackPath) && is_writable($fallbackPath)) session_save_path($fallbackPath);
        }
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || liquorsoftEnv('LIQUORSOFT_COOKIE_SECURE', '0') === '1',
            'use_strict_mode' => true,
        ]);
    }
}

function tableHasColumn(mysqli $connection, string $table, string $column): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    if (!$statement) {
        return false;
    }
    $statement->bind_param('ss', $table, $column);
    $statement->execute();
    $exists = ((int) ($statement->get_result()->fetch_assoc()['total'] ?? 0)) > 0;
    $statement->close();
    return $exists;
}

function paymentSimulatorEnabled(): bool
{
    return liquorsoftEnv('LIQUORSOFT_APP_ENV', 'production') === 'development'
        && liquorsoftEnv('LIQUORSOFT_PAYMENT_SIMULATOR', '0') === '1';
}

function isAdultBirthDate(string $value): bool
{
    $timezone = new DateTimeZone('America/Bogota');
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    return $birthDate !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $birthDate->format('Y-m-d') === $value
        && $birthDate->modify('+18 years') <= new DateTimeImmutable('today', $timezone);
}

function issueCsrfCookie(): void
{
    liquorsoftSessionStart();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || liquorsoftEnv('LIQUORSOFT_COOKIE_SECURE', '0') === '1';
    setcookie('liquorsoft_csrf', (string) $_SESSION['csrf_token'], [
        'expires' => time() + 7200, 'path' => '/', 'secure' => $secure,
        'httponly' => false, 'samesite' => 'Strict',
    ]);
}

function requireCsrfToken(): void
{
    liquorsoftSessionStart();
    $cookie = (string) ($_COOKIE['liquorsoft_csrf'] ?? '');
    $header = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($cookie === '' || $header === '' || $sessionToken === ''
        || !hash_equals($sessionToken, $cookie) || !hash_equals($sessionToken, $header)) {
        jsonResponse(403, ['message' => 'La sesión de compra caducó. Actualiza la página e inténtalo de nuevo.']);
    }
}

function productIcon(string $category): string
{
    return match (mb_strtolower(trim($category))) {
        'vino', 'vinos' => 'bi bi-wine',
        'cerveza', 'cervezas' => 'bi bi-cup-straw',
        'mixer', 'mixers' => 'bi bi-droplet-half',
        'gin', 'ginebra' => 'bi bi-cup-hot',
        default => 'bi bi-cup',
    };
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

function liquorsoftRateLimitCheck(string $scope, string $identity, int $maxAttempts, int $windowSeconds): bool
{
    $directory = sys_get_temp_dir() . '/liquorsoft-ratelimit';
    if (!is_dir($directory)) {
        @mkdir($directory, 0770, true);
    }
    $filePath = $directory . '/' . md5($scope . '|' . $identity) . '.json';
    $attempts = [];
    if (is_file($filePath)) {
        $decoded = json_decode((string) file_get_contents($filePath), true);
        if (is_array($decoded)) {
            $now = time();
            $attempts = array_values(array_filter(
                $decoded,
                static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds
            ));
        }
    }
    return count($attempts) < $maxAttempts;
}

function liquorsoftRateLimitHit(string $scope, string $identity, int $windowSeconds): void
{
    $directory = sys_get_temp_dir() . '/liquorsoft-ratelimit';
    if (!is_dir($directory)) {
        @mkdir($directory, 0770, true);
    }
    $filePath = $directory . '/' . md5($scope . '|' . $identity) . '.json';
    $attempts = [];
    if (is_file($filePath)) {
        $decoded = json_decode((string) file_get_contents($filePath), true);
        if (is_array($decoded)) {
            $now = time();
            $attempts = array_values(array_filter(
                $decoded,
                static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds
            ));
        }
    }
    $attempts[] = time();
    file_put_contents($filePath, json_encode($attempts), LOCK_EX);
}

function liquorsoftRateLimitClear(string $scope, string $identity): void
{
    $filePath = sys_get_temp_dir() . '/liquorsoft-ratelimit/' . md5($scope . '|' . $identity) . '.json';
    if (is_file($filePath)) {
        @unlink($filePath);
    }
}
