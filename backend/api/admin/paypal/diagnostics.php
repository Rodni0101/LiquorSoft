<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/paypal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}
$connection = databaseConnection();
requireRole($connection, ['Administrador']);
$connection->close();
header('Cache-Control: no-store');
$diagnostic = paypalCredentialDiagnostic();
jsonResponse(200, $diagnostic);
