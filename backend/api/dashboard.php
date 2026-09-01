<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$connection = databaseConnection();
requireRole($connection, ['Administrador', 'Supervisor', 'Vendedor', 'Bodega']);

function dashboardQuery(mysqli $connection, string $sql): mysqli_result
{
    $result = $connection->query($sql);
    if (!$result) {
        error_log('LiquorSoft dashboard SQL failed: ' . $connection->error . ' | SQL: ' . $sql);
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar las métricas del dashboard.']);
    }
    return $result;
}

$stats = dashboardQuery($connection,
    'SELECT COUNT(*) AS total_products,
            COALESCE(SUM(stock > stock_minimo), 0) AS available_products,
            COALESCE(SUM(stock = 0), 0) AS out_of_stock,
            COALESCE(SUM(stock), 0) AS total_units,
            COALESCE(SUM(stock * precio), 0) AS inventory_value,
            COALESCE(SUM(stock > 0 AND stock <= stock_minimo), 0) AS low_stock
     FROM productos WHERE estado = 1'
)->fetch_assoc() ?: [];

$salesTable = 'ventas';
$tablesResult = $connection->query("SHOW TABLES LIKE 'ventas'");
if (!$tablesResult || !$tablesResult->num_rows) {
    $salesTable = 'venta';
}
$columns = [];
$columnsResult = $connection->query("SHOW COLUMNS FROM `$salesTable`");
if ($columnsResult) {
    while ($column = $columnsResult->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
}
$totalColumn = in_array('total', $columns, true) ? 'total' : (in_array('total_venta', $columns, true) ? 'total_venta' : null);
$dateColumn = in_array('creado_en', $columns, true) ? 'creado_en' : (in_array('fecha_hora', $columns, true) ? 'fecha_hora' : null);
$statusCondition = in_array('estado', $columns, true)
    ? " AND estado = 'completada'"
    : (in_array('estado_pedido', $columns, true) ? " AND LOWER(estado_pedido) NOT IN ('anulado', 'cancelado', 'pendiente')" : '');

$sales = ['sales_today' => 0, 'sales_month' => 0, 'revenue_today' => 0, 'revenue_month' => 0];
if ($totalColumn && $dateColumn) {
    $sales = dashboardQuery($connection,
        "SELECT
          COALESCE(SUM(DATE(`$dateColumn`) = CURRENT_DATE), 0) AS sales_today,
          COALESCE(SUM(`$dateColumn` >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')), 0) AS sales_month,
          COALESCE(SUM(CASE WHEN DATE(`$dateColumn`) = CURRENT_DATE THEN `$totalColumn` ELSE 0 END), 0) AS revenue_today,
          COALESCE(SUM(CASE WHEN `$dateColumn` >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN `$totalColumn` ELSE 0 END), 0) AS revenue_month
         FROM `$salesTable`
         WHERE 1 = 1$statusCondition"
    )->fetch_assoc() ?: $sales;
} else {
    error_log('LiquorSoft dashboard: no se encontraron columnas compatibles para ventas.');
}

$users = dashboardQuery($connection,
    'SELECT COUNT(*) AS active_users FROM usuarios WHERE COALESCE(estado, 1) = 1'
)->fetch_assoc() ?: [];

$hasCategories = $connection->query("SHOW TABLES LIKE 'categorias'");
$recentSql = ($hasCategories && $hasCategories->num_rows)
    ? 'SELECT p.nombre AS name, COALESCE(c.nombre, \'Sin categoría\') AS category, p.stock, p.precio AS price
       FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
       WHERE p.estado = 1 ORDER BY p.fecha_creacion DESC, p.id DESC LIMIT 5'
    : 'SELECT p.nombre AS name, \'Sin categoría\' AS category, p.stock, p.precio AS price
       FROM productos p WHERE p.estado = 1 ORDER BY p.id DESC LIMIT 5';
$recentResult = dashboardQuery($connection, $recentSql);
$recent = [];
while ($product = $recentResult->fetch_assoc()) {
    $product['stock'] = (int) $product['stock'];
    $product['price'] = (float) $product['price'];
    $recent[] = $product;
}
$connection->close();

jsonResponse(200, [
    'success' => true,
    'stats' => [
        'totalProducts' => (int) ($stats['total_products'] ?? 0),
        'availableProducts' => (int) ($stats['available_products'] ?? 0),
        'outOfStock' => (int) ($stats['out_of_stock'] ?? 0),
        'totalUnits' => (int) ($stats['total_units'] ?? 0),
        'inventoryValue' => (float) ($stats['inventory_value'] ?? 0),
        'lowStock' => (int) ($stats['low_stock'] ?? 0),
        'salesToday' => (int) ($sales['sales_today'] ?? 0),
        'salesThisMonth' => (int) ($sales['sales_month'] ?? 0),
        'revenueToday' => (float) ($sales['revenue_today'] ?? 0),
        'revenueThisMonth' => (float) ($sales['revenue_month'] ?? 0),
        'activeUsers' => (int) ($users['active_users'] ?? 0),
    ],
    'recentProducts' => $recent,
]);
