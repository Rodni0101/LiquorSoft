<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/paypal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(405, ['message' => 'Método no permitido.']);
header('Cache-Control: no-store');
issueCsrfCookie();
$paypal = paypalConfiguration();
$methods = paymentSimulatorEnabled() ? ['nequi_simulado'] : [];
if ($paypal['enabled']) $methods[] = 'paypal';
jsonResponse(200, [
    'simulatorEnabled' => paymentSimulatorEnabled(),
    'paypalEnabled' => $paypal['enabled'],
    'paypalStatus' => $paypal['configurationStatus'],
    'paypalClientId' => $paypal['enabled'] ? $paypal['clientId'] : null,
    'paypalCurrency' => $paypal['currency'],
    'paypalCopPerUsd' => $paypal['enabled'] ? $paypal['copPerUsd'] : null,
    'methods' => $methods,
]);
