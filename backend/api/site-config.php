<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

header('Cache-Control: public, max-age=300');

$rawNumber = liquorsoftEnv('LIQUORSOFT_WHATSAPP_NUMBER', '');
$digits = preg_replace('/\D+/', '', $rawNumber) ?? '';
$whatsappNumber = strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
$message = trim(liquorsoftEnv('LIQUORSOFT_WHATSAPP_MESSAGE', 'Hola LiquorSoft, quiero información sobre sus productos.'));
if (mb_strlen($message) > 300) {
    $message = mb_substr($message, 0, 300);
}

jsonResponse(200, [
    'businessName' => liquorsoftEnv('LIQUORSOFT_BUSINESS_NAME', 'LiquorSoft'),
    'whatsappNumber' => $whatsappNumber,
    'whatsappMessage' => $message,
    'supportEmail' => liquorsoftEnv('LIQUORSOFT_SUPPORT_EMAIL', ''),
]);
