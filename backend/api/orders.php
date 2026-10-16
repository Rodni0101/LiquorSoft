<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$connection = databaseConnection();
$user = requireRole($connection, ['Administrador', 'Cliente']);

$userId = (int) $user['id'];
$hasPayment = tableHasColumn($connection, 'pedidos', 'metodo_pago');
$paymentSelect = $hasPayment ? ', p.metodo_pago AS paymentMethod' : ", '' AS paymentMethod";
$statement = $connection->prepare(
    "SELECT p.id, p.numero, p.total, p.estado AS status, p.creado_en AS createdAt $paymentSelect
     FROM pedidos p
     WHERE p.usuario_id = ?
     ORDER BY p.creado_en DESC
     LIMIT 40"
);
$statement->bind_param('i', $userId);
$statement->execute();
$result = $statement->get_result();
$orders = [];
$ids = [];
while ($row = $result->fetch_assoc()) {
    $id = (int) $row['id'];
    $ids[] = $id;
    $orders[$id] = [
        'id' => $id,
        'number' => (string) ($row['numero'] ?? ('PED-' . $id)),
        'total' => (float) $row['total'],
        'status' => (string) $row['status'],
        'createdAt' => (string) $row['createdAt'],
        'paymentMethod' => (string) ($row['paymentMethod'] ?? ''),
        'items' => [],
    ];
}
$statement->close();

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $details = $connection->prepare(
        "SELECT d.pedido_id, d.cantidad, d.precio_unitario, p.nombre
         FROM detalle_pedido d
         INNER JOIN productos p ON p.id = d.producto_id
         WHERE d.pedido_id IN ($placeholders)"
    );
    $details->bind_param($types, ...$ids);
    $details->execute();
    $detailResult = $details->get_result();
    while ($item = $detailResult->fetch_assoc()) {
        $orderId = (int) $item['pedido_id'];
        $orders[$orderId]['items'][] = [
            'name' => (string) $item['nombre'],
            'quantity' => (int) $item['cantidad'],
            'price' => (float) $item['precio_unitario'],
        ];
    }
    $details->close();
}

$connection->close();
jsonResponse(200, ['orders' => array_values($orders)]);
