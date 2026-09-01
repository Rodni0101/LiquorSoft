<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$connection = databaseConnection();
$summaryResult = $connection->query(
    'SELECT COUNT(*) AS products,
            COALESCE(SUM(stock > stock_minimo), 0) AS available_products,
            COALESCE(SUM(stock), 0) AS units
     FROM productos WHERE estado = 1'
);
$summary = $summaryResult ? ($summaryResult->fetch_assoc() ?: []) : [];
$salesTable = 'ventas';
$tablesResult = $connection->query("SHOW TABLES LIKE 'ventas'");
if (!$tablesResult || !$tablesResult->num_rows) {
    $salesTable = 'venta';
}
$salesColumns = [];
$columnsResult = $connection->query("SHOW COLUMNS FROM `$salesTable`");
if ($columnsResult) {
    while ($column = $columnsResult->fetch_assoc()) {
        $salesColumns[] = $column['Field'];
    }
}
$totalColumn = in_array('total', $salesColumns, true) ? 'total' : (in_array('total_venta', $salesColumns, true) ? 'total_venta' : null);
$dateColumn = in_array('creado_en', $salesColumns, true) ? 'creado_en' : (in_array('fecha_hora', $salesColumns, true) ? 'fecha_hora' : null);
$statusCondition = in_array('estado', $salesColumns, true) ? " AND estado = 'completada'" : (in_array('estado_pedido', $salesColumns, true) ? " AND estado_pedido NOT IN ('anulado', 'cancelado')" : '');
$monthlySalesResult = ($totalColumn && $dateColumn) ? $connection->query(
    "SELECT COALESCE(SUM(`$totalColumn`), 0) AS total FROM `$salesTable`
     WHERE `$dateColumn` >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')$statusCondition"
) : false;
$monthlySales = $monthlySalesResult ? ($monthlySalesResult->fetch_assoc() ?: []) : [];
$connection->close();

$productCount = (int) ($summary['products'] ?? 0);
$availability = $productCount > 0
    ? (int) round(((int) ($summary['available_products'] ?? 0) / $productCount) * 100)
    : 0;

jsonResponse(200, [
    'products' => $productCount,
    'monthlySales' => (float) ($monthlySales['total'] ?? 0),
    'availability' => min(100, $availability),
    'units' => (int) ($summary['units'] ?? 0),
]);
