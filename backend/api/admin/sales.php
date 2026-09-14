<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

$connection = databaseConnection();
requireRole($connection, ['Administrador']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $connection->close();
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$hasPayment = tableHasColumn($connection, 'ventas', 'metodo_pago');
$hasNotes = tableHasColumn($connection, 'ventas', 'notas');
$paymentSelect = $hasPayment ? ', v.metodo_pago AS paymentMethod' : ", '' AS paymentMethod";
$notesSelect = $hasNotes ? ', v.notas AS notes' : ", '' AS notes";

$result = $connection->query(
    "SELECT v.id, v.total, v.estado AS status, v.creado_en AS createdAt, v.usuario_id AS userId
            $paymentSelect $notesSelect,
            TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) AS customer
     FROM ventas v
     LEFT JOIN usuarios u ON u.id = v.usuario_id
     ORDER BY v.creado_en DESC, v.id DESC
     LIMIT 80"
);
if (!$result) {
    $connection->close();
    jsonResponse(500, ['message' => 'No fue posible cargar las ventas.']);
}

$sales = [];
$ids = [];
while ($row = $result->fetch_assoc()) {
    $id = (int) $row['id'];
    $ids[] = $id;
    $sales[$id] = [
        'id' => $id,
        'total' => (float) $row['total'],
        'status' => (string) $row['status'],
        'createdAt' => (string) $row['createdAt'],
        'customer' => trim((string) $row['customer']) ?: 'Invitado',
        'paymentMethod' => (string) ($row['paymentMethod'] ?? ''),
        'notes' => (string) ($row['notes'] ?? ''),
        'items' => [],
    ];
}

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $details = $connection->prepare(
        "SELECT d.venta_id, d.cantidad, d.precio_unitario, p.nombre
         FROM detalle_venta d
         INNER JOIN productos p ON p.id = d.producto_id
         WHERE d.venta_id IN ($placeholders)
         ORDER BY d.id ASC"
    );
    $details->bind_param($types, ...$ids);
    $details->execute();
    $detailResult = $details->get_result();
    while ($item = $detailResult->fetch_assoc()) {
        $saleId = (int) $item['venta_id'];
        if (!isset($sales[$saleId])) {
            continue;
        }
        $sales[$saleId]['items'][] = [
            'name' => (string) $item['nombre'],
            'quantity' => (int) $item['cantidad'],
            'price' => (float) $item['precio_unitario'],
        ];
    }
    $details->close();
}

$connection->close();
jsonResponse(200, ['sales' => array_values($sales)]);
