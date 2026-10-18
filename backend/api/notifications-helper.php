<?php
declare(strict_types=1);

function createNotification(
    mysqli $connection,
    int $userId,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?int $pedidoId = null
): bool {
    $statement = $connection->prepare(
        'INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, pedido_id, leida, creado_en)
         VALUES (?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)'
    );
    if (!$statement) {
        error_log('createNotification prepare failed: ' . $connection->error);
        return false;
    }
    $statement->bind_param('isssi', $userId, $tipo, $titulo, $mensaje, $pedidoId);
    $ok = $statement->execute();
    if (!$ok) {
        error_log('createNotification execute failed: ' . $statement->error);
    }
    $statement->close();
    return $ok;
}

function notifyAdmins(
    mysqli $connection,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?int $pedidoId = null
): void {
    $adminQuery = $connection->query(
        "SELECT u.id FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE LOWER(TRIM(r.nombre)) = 'administrador' AND COALESCE(u.estado, 1) = 1"
    );
    if ($adminQuery) {
        while ($admin = $adminQuery->fetch_assoc()) {
            createNotification($connection, (int) $admin['id'], $tipo, $titulo, $mensaje, $pedidoId);
        }
        $adminQuery->close();
    }
}

