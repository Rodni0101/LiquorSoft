<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
liquorsoftSessionStart();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
}
setcookie('liquorsoft_csrf', '', [
    'expires' => time() - 3600, 'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || liquorsoftEnv('LIQUORSOFT_COOKIE_SECURE', '0') === '1',
    'httponly' => false, 'samesite' => 'Strict',
]);
session_destroy();
jsonResponse(200, ['message' => 'Sesión cerrada.']);
