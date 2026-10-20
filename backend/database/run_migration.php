<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';

$connection = databaseConnection();
$sql = file_get_contents(__DIR__ . '/migrations/012_notifications_system.sql');

if (!$sql) {
    echo "No se pudo leer el archivo de migración.\n";
    exit(1);
}

$error = null;
if ($connection->multi_query($sql)) {
    do {
        if ($result = $connection->store_result()) {
            $result->free();
        }
        if ($connection->errno) {
            $error = $connection->error;
            break;
        }
    } while ($connection->more_results() && $connection->next_result());
} else {
    $error = $connection->error;
}

if ($error !== null) {
    echo "Error ejecutando migración: {$error}\n";
    exit(1);
}

echo "Migración 012 ejecutada correctamente.\n";

$connection->close();

