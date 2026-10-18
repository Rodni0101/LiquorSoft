<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';

$connection = databaseConnection();
$sql = file_get_contents(__DIR__ . '/migrations/012_notifications_system.sql');

if (!$sql) {
    echo "No se pudo leer el archivo de migración.\n";
    exit(1);
}

if ($connection->multi_query($sql)) {
    do {
        if ($result = $connection->store_result()) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());
    echo "Migración 012 ejecutada correctamente.\n";
} else {
    echo "Error ejecutando migración: " . $connection->error . "\n";
    exit(1);
}

$connection->close();

