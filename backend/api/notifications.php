<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$connection = databaseConnection();
$user = currentUser($connection);

if (!$user) {
    $connection->close();
    jsonResponse(401, ['message' => 'Debes iniciar sesión para acceder a las notificaciones.']);
}

$userId = (int) $user['id'];
$method = $_SERVER['REQUEST_METHOD'];

if (!tableHasColumn($connection, 'notificaciones', 'id')) {
    $connection->close();
    jsonResponse(503, ['message' => 'El módulo de notificaciones no está disponible en esta base de datos.']);
}

if ($method === 'GET') {
    $statement = $connection->prepare(
        'SELECT n.id, n.tipo, n.titulo, n.mensaje, n.pedido_id, n.leida, n.creado_en, n.leida_en,
                p.numero AS numero_pedido
         FROM notificaciones n
         LEFT JOIN pedidos p ON p.id = n.pedido_id
         WHERE n.usuario_id = ?
         ORDER BY n.creado_en DESC, n.id DESC
         LIMIT 60'
    );
    if (!$statement) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible consultar las notificaciones.']);
    }
    $statement->bind_param('i', $userId);
    $statement->execute();
    $result = $statement->get_result();
    $notifications = [];
    $unreadCount = 0;

    while ($row = $result->fetch_assoc()) {
        $isRead = (bool) $row['leida'];
        if (!$isRead) {
            $unreadCount++;
        }
        $notifications[] = [
            'id' => (int) $row['id'],
            'type' => (string) $row['tipo'],
            'title' => (string) $row['titulo'],
            'message' => (string) $row['mensaje'],
            'orderId' => $row['pedido_id'] !== null ? (int) $row['pedido_id'] : null,
            'orderNumber' => $row['numero_pedido'] !== null ? (string) $row['numero_pedido'] : null,
            'read' => $isRead,
            'createdAt' => (string) $row['creado_en'],
            'readAt' => $row['leida_en'] !== null ? (string) $row['leida_en'] : null,
        ];
    }
    $statement->close();
    $connection->close();

    jsonResponse(200, [
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
    ]);
}

if ($method === 'PATCH' || $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $connection->close();
        jsonResponse(400, ['message' => 'Formato de solicitud no válido.']);
    }

    $markAll = (bool) ($body['all'] ?? false);
    $notificationId = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);

    if ($markAll) {
        $stmt = $connection->prepare(
            'UPDATE notificaciones SET leida = 1, leida_en = CURRENT_TIMESTAMP
             WHERE usuario_id = ? AND leida = 0'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    } elseif ($notificationId) {
        $stmt = $connection->prepare(
            'UPDATE notificaciones SET leida = 1, leida_en = CURRENT_TIMESTAMP
             WHERE id = ? AND usuario_id = ?'
        );
        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
        $stmt->close();
    } else {
        $connection->close();
        jsonResponse(422, ['message' => 'Especifica una notificación o indica marcar todas.']);
    }

    // Calcular el conteo actualizado de no leídas
    $countStmt = $connection->prepare('SELECT COUNT(*) AS total FROM notificaciones WHERE usuario_id = ? AND leida = 0');
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $unreadCount = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
    $connection->close();

    jsonResponse(200, [
        'message' => $markAll ? 'Todas las notificaciones fueron marcadas como leídas.' : 'Notificación actualizada.',
        'unreadCount' => $unreadCount,
    ]);
}

$connection->close();
jsonResponse(405, ['message' => 'Método no permitido.']);
