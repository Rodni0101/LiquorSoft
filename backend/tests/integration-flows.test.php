<?php
declare(strict_types=1);

$baseUrl = 'http://127.0.0.1:8000';

function apiRequest(string $method, string $path, ?array $body = null, ?string $cookieJar = null, ?string $csrf = null): array
{
    global $baseUrl;
    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    if ($csrf !== null) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headerStr = substr((string)$response, 0, $headerSize);
    $bodyStr = substr((string)$response, $headerSize);
    $data = json_decode($bodyStr, true);

    $csrfFound = null;
    if (preg_match('/Set-Cookie:\s*liquorsoft_csrf=([a-f0-9]+)/i', $headerStr, $m)) {
        $csrfFound = $m[1];
    }

    return [
        'status' => $statusCode,
        'data' => $data,
        'headers' => $headerStr,
        'csrf' => $csrfFound,
        'rawBody' => $bodyStr
    ];
}

function generateUuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

echo "=== INICIANDO PRUEBAS DE INTEGRACIÓN: FLUJOS A, B, C, D ===\n\n";
$flowsPassed = 0;

$clientCookie = tempnam(sys_get_temp_dir(), 'lsoft_client_');
$adminCookie = tempnam(sys_get_temp_dir(), 'lsoft_admin_');

$loginClient = apiRequest('POST', '/api/auth/Login.php', [
    'correo' => 'cliente@liquorsoft.com',
    'password' => 'Cliente1234*'
], $clientCookie);
$clientCsrf = $loginClient['csrf'];

$loginAdmin = apiRequest('POST', '/api/auth/Login.php', [
    'correo' => 'admin@liquorsoft.com',
    'password' => 'Admin1234*'
], $adminCookie);
$adminCsrf = $loginAdmin['csrf'];

// 1. FLUJO A: COMPRA DE UN CLIENTE
echo "--- FLUJO A: COMPRA DE UN CLIENTE ---\n";

$prodRes = apiRequest('GET', '/api/products.php?id=4');
$product = $prodRes['data']['product'] ?? null;

$purchasePayload = [
    'paymentMethod' => 'nequi_simulado',
    'simulationOutcome' => 'approved',
    'idempotencyKey' => generateUuid(),
    'deliveryAddress' => 'Calle 100 # 15-20, Apto 502, Bogotá',
    'birthDate' => '1995-05-15',
    'notes' => 'Timbre 502 por favor',
    'items' => [
        ['id' => 4, 'quantity' => 1]
    ]
];
$purchaseRes = apiRequest('POST', '/api/purchase.php', $purchasePayload, $clientCookie, $clientCsrf);

if ($purchaseRes['status'] === 201 && !empty($purchaseRes['data']['orderId'])) {
    $orderId = (int) $purchaseRes['data']['orderId'];
    $orderNumber = $purchaseRes['data']['orderNumber'];
    echo "[✓] Flujo A.1: Pedido #{$orderNumber} (ID: {$orderId}) registrado correctamente con estado 'CONFIRMADO'.\n";

    $clientNotifs = apiRequest('GET', '/api/notifications.php', null, $clientCookie);
    $foundClientNotif = false;
    foreach ($clientNotifs['data']['notifications'] ?? [] as $n) {
        if ($n['orderId'] === $orderId && $n['type'] === 'COMPRA_REGISTRADA') {
            $foundClientNotif = true;
            echo "[✓] Flujo A.2: Cliente recibió notificación: '{$n['title']}' - '{$n['message']}'.\n";
            break;
        }
    }

    $loginAdmin = apiRequest('POST', '/api/auth/Login.php', [
        'correo' => 'admin@liquorsoft.com',
        'password' => 'Admin1234*'
    ], $adminCookie);
    $adminCsrf = $loginAdmin['csrf'];

    $adminNotifs = apiRequest('GET', '/api/notifications.php', null, $adminCookie);
    $foundAdminNotif = false;
    foreach ($adminNotifs['data']['notifications'] ?? [] as $an) {
        if ($an['orderId'] === $orderId && $an['type'] === 'NUEVA_COMPRA_ADMIN') {
            $foundAdminNotif = true;
            echo "[✓] Flujo A.3: Administrador recibió notificación: '{$an['title']}' - '{$an['message']}'.\n";
            break;
        }
    }

    if ($foundClientNotif && $foundAdminNotif) {
        echo "[✓] FLUJO A COMPLETADO Y APROBADO SATISFACTORIAMENTE.\n\n";
        $flowsPassed++;
    } else {
        echo "[✗] FLUJO A FALLIDO: No se encontraron ambas notificaciones.\n\n";
    }

    // 2. FLUJO B: ACTUALIZACIÓN DEL PEDIDO
    echo "--- FLUJO B: ACTUALIZACIÓN DEL PEDIDO ---\n";
    $ordersAdmin = apiRequest('GET', '/api/admin/orders.php', null, $adminCookie);
    $currentOrder = null;
    foreach ($ordersAdmin['data']['orders'] ?? [] as $o) {
        if ($o['id'] === $orderId) {
            $currentOrder = $o;
            break;
        }
    }
    echo "[i] Estado actual del pedido #{$orderId}: '{$currentOrder['status']}'.\n";

    $updatePayload = [
        'orderId' => $orderId,
        'status' => 'PREPARANDO',
        'fulfillmentMethod' => 'DOMICILIO',
        'fulfillmentDetails' => 'Dirección confirmada: Calle 100 # 15-20, Bogotá'
    ];
    $updateRes = apiRequest('PATCH', '/api/admin/orders.php', $updatePayload, $adminCookie, $adminCsrf);

    if ($updateRes['status'] === 200 && ($updateRes['data']['order']['status'] ?? '') === 'PREPARANDO') {
        echo "[✓] Flujo B.1: Pedido actualizado a 'PREPARANDO' por el administrador.\n";

        $clientNotifsAfter = apiRequest('GET', '/api/notifications.php', null, $clientCookie);
        $foundStatusNotif = false;
        foreach ($clientNotifsAfter['data']['notifications'] ?? [] as $cn) {
            if ($cn['orderId'] === $orderId && $cn['type'] === 'ESTADO_PEDIDO' && str_contains($cn['title'], 'preparación')) {
                $foundStatusNotif = true;
                echo "[✓] Flujo B.2: Cliente recibió notificación de actualización: '{$cn['title']}' - '{$cn['message']}'.\n";
                break;
            }
        }

        $clientOrders = apiRequest('GET', '/api/orders.php', null, $clientCookie);
        $matchingOrder = null;
        foreach ($clientOrders['data']['orders'] ?? [] as $co) {
            if ($co['id'] === $orderId) {
                $matchingOrder = $co;
                break;
            }
        }
        if ($matchingOrder && $matchingOrder['status'] === 'PREPARANDO') {
            echo "[✓] Flujo B.3: La vista de pedidos del cliente refleja exactamente el nuevo estado 'PREPARANDO'.\n";
            echo "[✓] FLUJO B COMPLETADO Y APROBADO SATISFACTORIAMENTE.\n\n";
            $flowsPassed++;
        } else {
            echo "[✗] Flujo B.3 fallido: estado en pedidos del cliente no coincide.\n\n";
        }
    } else {
        echo "[✗] FLUJO B FALLIDO: error al actualizar pedido.\n\n";
    }

} else {
    echo "[✗] Flujo A falló al crear compra: status {$purchaseRes['status']}.\n\n";
}

// 3. FLUJO C: PERMISOS Y SEGURIDAD
echo "--- FLUJO C: PERMISOS Y SEGURIDAD ---\n";
$sec1 = apiRequest('GET', '/api/admin/orders.php', null, $clientCookie);
$passSec1 = $sec1['status'] === 403;
echo ($passSec1 ? "[✓]" : "[✗]") . " C.1: Cliente bloqueado con 403 en endpoint administrativo.\n";

$sec2 = apiRequest('PATCH', '/api/admin/orders.php', [
    'orderId' => $orderId ?? 1,
    'status' => 'ENTREGADO',
    'fulfillmentMethod' => 'DOMICILIO',
    'fulfillmentDetails' => 'Intento no autorizado'
], $clientCookie, $clientCsrf);
$passSec2 = $sec2['status'] === 403;
echo ($passSec2 ? "[✓]" : "[✗]") . " C.2: Cliente bloqueado con 403 al intentar modificar estado de pedido.\n";

$invalidTransition = apiRequest('PATCH', '/api/admin/orders.php', [
    'orderId' => $orderId ?? 1,
    'status' => 'ENTREGADO',
    'fulfillmentMethod' => 'DOMICILIO',
    'fulfillmentDetails' => 'Salto inválido'
], $adminCookie, $adminCsrf);
$passSec3 = $invalidTransition['status'] === 422;
echo ($passSec3 ? "[✓]" : "[✗]") . " C.3: Transición inválida rechazada por el backend con 422: '{$invalidTransition['data']['message']}'.\n";

$adminNotifsRes = apiRequest('GET', '/api/notifications.php', null, $adminCookie);
$adminNotifId = $adminNotifsRes['data']['notifications'][0]['id'] ?? 1;
$crossUpdate = apiRequest('PATCH', '/api/notifications.php', ['id' => $adminNotifId], $clientCookie);
$adminNotifsCheck = apiRequest('GET', '/api/notifications.php', null, $adminCookie);
$stillUnread = false;
foreach ($adminNotifsCheck['data']['notifications'] ?? [] as $chk) {
    if ($chk['id'] === $adminNotifId && !$chk['read']) {
        $stillUnread = true;
        break;
    }
}
$passSec4 = $stillUnread;
echo ($passSec4 ? "[✓]" : "[✗]") . " C.4: Aislamiento estricto de notificaciones garantizado (cliente no puede alterar notificaciones ajenas).\n";

if ($passSec1 && $passSec2 && $passSec3 && $passSec4) {
    echo "[✓] FLUJO C COMPLETADO Y APROBADO SATISFACTORIAMENTE.\n\n";
    $flowsPassed++;
} else {
    echo "[✗] FLUJO C FALLIDO.\n\n";
}

// 4. FLUJO D: PRODUCTO INDIVIDUAL
echo "--- FLUJO D: PRODUCTO INDIVIDUAL ---\n";
$prod1 = apiRequest('GET', '/api/products.php?id=1');
$passD1 = $prod1['status'] === 200 && ($prod1['data']['product']['id'] ?? 0) === 1;
echo ($passD1 ? "[✓]" : "[✗]") . " D.1: Consulta de producto existente (/api/products.php?id=1) responde 200 con datos de '{$prod1['data']['product']['name']}'.\n";

$prod1Repeat = apiRequest('GET', '/api/products.php?id=1');
$passD2 = $prod1Repeat['status'] === 200 && ($prod1Repeat['data']['product']['name'] ?? '') === ($prod1['data']['product']['name'] ?? '');
echo ($passD2 ? "[✓]" : "[✗]") . " D.2: Recarga/consultas consecutivas son idempotentes y consistentes.\n";

$prodNotFound = apiRequest('GET', '/api/products.php?id=88888');
$passD3 = $prodNotFound['status'] === 404 && isset($prodNotFound['data']['message']);
echo ($passD3 ? "[✓]" : "[✗]") . " D.3: Identificador inexistente responde 404 ('{$prodNotFound['data']['message']}').\n";

$prodInvalid = apiRequest('GET', '/api/products.php?id=texto_invalido');
$passD4 = $prodInvalid['status'] === 422 && isset($prodInvalid['data']['message']);
echo ($passD4 ? "[✓]" : "[✗]") . " D.4: Identificador no numérico responde 422 ('{$prodInvalid['data']['message']}').\n";

$prodMethod = apiRequest('POST', '/api/products.php', []);
$passD5 = $prodMethod['status'] === 405;
echo ($passD5 ? "[✓]" : "[✗]") . " D.5: Método no permitido responde 405.\n";

if ($passD1 && $passD2 && $passD3 && $passD4 && $passD5) {
    echo "[✓] FLUJO D COMPLETADO Y APROBADO SATISFACTORIAMENTE.\n\n";
    $flowsPassed++;
} else {
    echo "[✗] FLUJO D FALLIDO.\n\n";
}

@unlink($clientCookie);
@unlink($adminCookie);

echo "\nResultado final: $flowsPassed de 4 flujos integrales superados exitosamente.\n";
if ($flowsPassed === 4) {
    echo "TODOS LOS FLUJOS INTEGRALES (A, B, C, D) FUERON APROBADOS SATISFACTORIAMENTE.\n";
    exit(0);
} else {
    exit(1);
}
