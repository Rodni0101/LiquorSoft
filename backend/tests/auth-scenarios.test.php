<?php
declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8000';

function httpRequest(string $method, string $path, ?array $body = null, ?string $cookieJar = null): array
{
    global $baseUrl;
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        return [
            'status' => 0,
            'data' => ['message' => 'cURL error: ' . $err],
            'headers' => '',
            'rawBody' => ''
        ];
    }
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headerStr = substr((string)$response, 0, $headerSize);
    $bodyStr = substr((string)$response, $headerSize);
    $data = json_decode($bodyStr, true);

    return [
        'status' => $statusCode,
        'data' => $data,
        'headers' => $headerStr,
        'rawBody' => $bodyStr
    ];
}

echo "=== INICIANDO 10 PRUEBAS OBLIGATORIAS DE AUTENTICACIÓN ===\n\n";
$testsPassed = 0;

// Escenario 1: Consultar la sesión sin iniciar sesión
$cookieFile = tempnam(sys_get_temp_dir(), 'lsoft_cookie_');
$res1 = httpRequest('GET', '/api/auth/me.php');
if ($res1['status'] === 401 && isset($res1['data']['message'])) {
    echo "[✓] Escenario 1 superado: GET /api/auth/me.php responde 401 ('{$res1['data']['message']}') cuando no hay sesión activa.\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 1 fallido: esperaba 401, obtuvo {$res1['status']}.\n";
}

// Escenario 2: Iniciar sesión con credenciales válidas
$res2 = httpRequest('POST', '/api/auth/Login.php', [
    'correo' => 'cliente@liquorsoft.com',
    'password' => 'Cliente1234*'
], $cookieFile);
if ($res2['status'] === 200 && isset($res2['data']['user']['id'])) {
    echo "[✓] Escenario 2 superado: Login válido responde 200 con usuario ID {$res2['data']['user']['id']}.\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 2 fallido: esperaba 200, obtuvo {$res2['status']}.\n";
}

// Escenario 3: Iniciar sesión con credenciales incorrectas
$res3 = httpRequest('POST', '/api/auth/Login.php', [
    'correo' => 'cliente@liquorsoft.com',
    'password' => 'ClaveErronea999*'
]);
if ($res3['status'] === 401 && isset($res3['data']['message'])) {
    echo "[✓] Escenario 3 superado: Login con clave incorrecta responde 401 ('{$res3['data']['message']}').\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 3 fallido: esperaba 401, obtuvo {$res3['status']}.\n";
}

// Escenario 4: Consultar /api/auth/me.php después de una autenticación exitosa
$res4 = httpRequest('GET', '/api/auth/me.php', null, $cookieFile);
if ($res4['status'] === 200 && ($res4['data']['user']['email'] ?? '') === 'cliente@liquorsoft.com') {
    echo "[✓] Escenario 4 superado: Sesión activa confirmada en me.php para '{$res4['data']['user']['email']}' con rol '{$res4['data']['user']['role']}'.\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 4 fallido: me.php no devolvió la sesión activa esperada (status: {$res4['status']}).\n";
}

// Escenario 5: Navegar a una ruta protegida con una sesión válida
$res5 = httpRequest('GET', '/api/orders.php', null, $cookieFile);
if ($res5['status'] === 200 && isset($res5['data']['orders'])) {
    echo "[✓] Escenario 5 superado: Acceso autorizado a ruta protegida de cliente (/api/orders.php).\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 5 fallido: ruta protegida respondió {$res5['status']}.\n";
}

// Escenario 6: Intentar acceder a una ruta administrativa como cliente
$res6 = httpRequest('GET', '/api/admin/orders.php', null, $cookieFile);
if ($res6['status'] === 403) {
    echo "[✓] Escenario 6 superado: Cliente bloqueado con 403 al intentar acceder a /api/admin/orders.php ('{$res6['data']['message']}').\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 6 fallido: esperaba 403 para cliente en ruta admin, obtuvo {$res6['status']}.\n";
}

// Escenario 7: Cerrar sesión y volver a consultar la sesión
$res7Logout = httpRequest('POST', '/api/auth/logout.php', [], $cookieFile);
$res7Me = httpRequest('GET', '/api/auth/me.php', null, $cookieFile);
if ($res7Logout['status'] === 200 && $res7Me['status'] === 401) {
    echo "[✓] Escenario 7 superado: Sesión destruida exitosamente; consulta posterior devuelve 401.\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 7 fallido: logout status {$res7Logout['status']}, me status {$res7Me['status']}.\n";
}

// Escenario 8: Recargar la página después de iniciar sesión
$cookieFile8 = tempnam(sys_get_temp_dir(), 'lsoft_cookie8_');
httpRequest('POST', '/api/auth/Login.php', [
    'correo' => 'admin@liquorsoft.com',
    'password' => 'Admin1234*'
], $cookieFile8);
$reload1 = httpRequest('GET', '/api/auth/me.php', null, $cookieFile8);
$reload2 = httpRequest('GET', '/api/auth/me.php', null, $cookieFile8);
if ($reload1['status'] === 200 && $reload2['status'] === 200 && ($reload2['data']['user']['email'] ?? '') === 'admin@liquorsoft.com') {
    echo "[✓] Escenario 8 superado: La sesión se mantiene consistente tras múltiples lecturas sucesivas (simulación de recarga F5).\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 8 fallido en recarga de sesión.\n";
}

// Escenario 9: Comprobar el comportamiento al expirar o perder la sesión
$fakeCookieFile = tempnam(sys_get_temp_dir(), 'lsoft_fake_');
file_put_contents($fakeCookieFile, "localhost\tFALSE\t/\tFALSE\t0\tPHPSESSID\tinvalid_tampered_session_token\n");
$res9 = httpRequest('GET', '/api/auth/me.php', null, $fakeCookieFile);
if ($res9['status'] === 401) {
    echo "[✓] Escenario 9 superado: Token de sesión alterado o expirado es rechazado con 401.\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 9 fallido: esperaba 401 ante sesión inválida, obtuvo {$res9['status']}.\n";
}

// Escenario 10: Verificar que los errores se gestionen correctamente en el frontend
$res10 = httpRequest('POST', '/api/auth/Login.php', [
    'correo' => 'no_es_un_correo',
    'password' => ''
]);
if ($res10['status'] === 422 && !empty($res10['data']['message'])) {
    echo "[✓] Escenario 10 superado: Validación de campos devuelve 422 con mensaje legible para la UI: '{$res10['data']['message']}'.\n";
    $testsPassed++;
} else {
    echo "[✗] Escenario 10 fallido: validación esperaba 422, obtuvo {$res10['status']}.\n";
}

@unlink($cookieFile);
@unlink($cookieFile8);
@unlink($fakeCookieFile);

echo "\nResultado: $testsPassed de 10 pruebas superadas.\n";
if ($testsPassed === 10) {
    echo "TODAS LAS PRUEBAS DE AUTENTICACIÓN FUERON APROBADAS SATISFACTORIAMENTE.\n";
    exit(0);
} else {
    exit(1);
}

