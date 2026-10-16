<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(405, ['message' => 'Método no permitido.']);
header('Cache-Control: no-store');
issueCsrfCookie();
jsonResponse(200, [
    'simulatorEnabled' => paymentSimulatorEnabled(),
    'methods' => paymentSimulatorEnabled() ? ['nequi_simulado'] : [],
]);
