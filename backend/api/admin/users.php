<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

$connection = databaseConnection();
$admin = requirePermission($connection, 'usuarios');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usersResult = $connection->query(
        'SELECT u.id, u.nombre, u.apellido, u.correo, u.telefono, u.usuario, u.estado, r.id AS rol_id, r.nombre AS rol
         FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id ORDER BY u.nombre, u.apellido'
    );
    $rolesResult = $connection->query("SELECT id, nombre FROM roles WHERE COALESCE(estado, 1) = 1 ORDER BY id");
    if (!$usersResult || !$rolesResult) {
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible cargar los usuarios.']);
    }
    $users = [];
    while ($user = $usersResult->fetch_assoc()) {
        $users[] = [
            'id' => (int) $user['id'],
            'name' => trim($user['nombre'] . ' ' . ($user['apellido'] ?? '')),
            'email' => $user['correo'],
            'phone' => (string) ($user['telefono'] ?? ''),
            'username' => (string) ($user['usuario'] ?? ''),
            'role' => (string) ($user['rol'] ?? ''),
            'roleId' => (int) ($user['rol_id'] ?? 0),
            'active' => (bool) ($user['estado'] ?? 1),
        ];
    }
    $roles = [];
    while ($role = $rolesResult->fetch_assoc()) {
        $roles[] = ['id' => (int) $role['id'], 'name' => (string) $role['nombre']];
    }
    $connection->close();
    jsonResponse(200, ['users' => $users, 'roles' => $roles]);
}

// Crear un nuevo usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_GET['action'])) {
    requireCsrfToken();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $connection->close();
        jsonResponse(400, ['message' => 'Formato JSON inválido.']);
    }
    $nombre = trim((string) ($body['nombre'] ?? ''));
    $apellido = trim((string) ($body['apellido'] ?? ''));
    $telefono = trim((string) ($body['telefono'] ?? ''));
    $correo = strtolower(trim((string) ($body['correo'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    $roleId = filter_var($body['rol_id'] ?? $body['roleId'] ?? null, FILTER_VALIDATE_INT);

    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
        $connection->close();
        jsonResponse(422, ['message' => 'El nombre es obligatorio (mínimo 2 caracteres).']);
    }
    if (mb_strlen($apellido) < 2 || mb_strlen($apellido) > 100) {
        $connection->close();
        jsonResponse(422, ['message' => 'El apellido es obligatorio (mínimo 2 caracteres).']);
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
        $connection->close();
        jsonResponse(422, ['message' => 'Correo electrónico no válido.']);
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $connection->close();
        jsonResponse(422, ['message' => 'La contraseña debe tener entre 8 y 72 caracteres.']);
    }
    if (!$roleId || $roleId < 1) {
        $connection->close();
        jsonResponse(422, ['message' => 'Debe seleccionar un rol válido.']);
    }

    // Verificar que el rol exista y esté activo
    $roleStmt = $connection->prepare('SELECT id, nombre FROM roles WHERE id = ? AND COALESCE(estado, 1) = 1 LIMIT 1');
    $roleStmt->bind_param('i', $roleId);
    $roleStmt->execute();
    $role = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$role) {
        $connection->close();
        jsonResponse(404, ['message' => 'El rol seleccionado no existe o está inactivo.']);
    }

    // Verificar correo duplicado
    $dup = $connection->prepare('SELECT id FROM usuarios WHERE correo = ? LIMIT 1');
    $dup->bind_param('s', $correo);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        $dup->close();
        $connection->close();
        jsonResponse(409, ['message' => 'Ya existe una cuenta con ese correo electrónico.']);
    }
    $dup->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $usernameBase = preg_replace('/[^a-z0-9._-]/', '', strtolower((string) strstr($correo, '@', true))) ?: 'usuario';
    $username = substr($usernameBase, 0, 40) . '_' . bin2hex(random_bytes(4));

    if (tableHasColumn($connection, 'usuarios', 'password')) {
        $stmt = $connection->prepare(
            'INSERT INTO usuarios (rol_id, nombre, apellido, telefono, correo, password_hash, password, usuario, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->bind_param('isssssss', $roleId, $nombre, $apellido, $telefono, $correo, $passwordHash, $passwordHash, $username);
    } else {
        $stmt = $connection->prepare(
            'INSERT INTO usuarios (rol_id, nombre, apellido, telefono, correo, password_hash, usuario, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->bind_param('issssss', $roleId, $nombre, $apellido, $telefono, $correo, $passwordHash, $username);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible crear el usuario.']);
    }
    $newId = (int) $connection->insert_id;
    $stmt->close();
    $connection->close();
    jsonResponse(201, [
        'message' => 'Usuario creado correctamente.',
        'user' => ['id' => $newId, 'name' => trim($nombre . ' ' . $apellido), 'email' => $correo, 'role' => $role['nombre']],
    ]);
}

// Actualizar un usuario existente (PATCH) o dar de baja (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    requireCsrfToken();
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $connection->close();
        jsonResponse(400, ['message' => 'Formato JSON inválido.']);
    }
    $userId = (int) ($body['userId'] ?? 0);
    if ($userId < 1) {
        $connection->close();
        jsonResponse(422, ['message' => 'El usuario es obligatorio.']);
    }

    $targetStmt = $connection->prepare('SELECT id, rol_id, estado FROM usuarios WHERE id = ? LIMIT 1');
    $targetStmt->bind_param('i', $userId);
    $targetStmt->execute();
    $target = $targetStmt->get_result()->fetch_assoc();
    $targetStmt->close();
    if (!$target) {
        $connection->close();
        jsonResponse(404, ['message' => 'El usuario no existe.']);
    }
    $isSelf = $userId === (int) $admin['id'];

    $fields = [];
    $types = '';
    $params = [];
    $roleName = null;

    $roleId = filter_var($body['roleId'] ?? $body['rol_id'] ?? null, FILTER_VALIDATE_INT);
    if ($roleId) {
        $roleStmt = $connection->prepare('SELECT id, nombre FROM roles WHERE id = ? AND COALESCE(estado, 1) = 1 LIMIT 1');
        $roleStmt->bind_param('i', $roleId);
        $roleStmt->execute();
        $role = $roleStmt->get_result()->fetch_assoc();
        $roleStmt->close();
        if (!$role) {
            $connection->close();
            jsonResponse(404, ['message' => 'El rol seleccionado no existe o está inactivo.']);
        }
        if ($isSelf && $roleId !== (int) $target['rol_id']) {
            $connection->close();
            jsonResponse(422, ['message' => 'No puedes cambiar el rol de tu propia cuenta.']);
        }
        $roleName = (string) $role['nombre'];
        $fields[] = 'rol_id = ?';
        $types .= 'i';
        $params[] = $roleId;
    }

    if (array_key_exists('active', $body) || array_key_exists('activo', $body)) {
        $active = (int) (bool) ($body['active'] ?? $body['activo']);
        if ($isSelf && $active === 0) {
            $connection->close();
            jsonResponse(422, ['message' => 'No puedes desactivar tu propia cuenta.']);
        }
        $fields[] = 'estado = ?';
        $types .= 'i';
        $params[] = $active;
    }

    if (array_key_exists('nombre', $body)) {
        $nombre = trim((string) $body['nombre']);
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            $connection->close();
            jsonResponse(422, ['message' => 'El nombre debe tener entre 2 y 100 caracteres.']);
        }
        $fields[] = 'nombre = ?';
        $types .= 's';
        $params[] = $nombre;
    }
    if (array_key_exists('apellido', $body)) {
        $apellido = trim((string) $body['apellido']);
        if (mb_strlen($apellido) < 2 || mb_strlen($apellido) > 100) {
            $connection->close();
            jsonResponse(422, ['message' => 'El apellido debe tener entre 2 y 100 caracteres.']);
        }
        $fields[] = 'apellido = ?';
        $types .= 's';
        $params[] = $apellido;
    }
    if (array_key_exists('correo', $body)) {
        $correo = strtolower(trim((string) $body['correo']));
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
            $connection->close();
            jsonResponse(422, ['message' => 'Correo electrónico no válido.']);
        }
        $dup = $connection->prepare('SELECT id FROM usuarios WHERE correo = ? AND id <> ? LIMIT 1');
        $dup->bind_param('si', $correo, $userId);
        $dup->execute();
        $emailTaken = $dup->get_result()->num_rows > 0;
        $dup->close();
        if ($emailTaken) {
            $connection->close();
            jsonResponse(409, ['message' => 'Ya existe otra cuenta con ese correo electrónico.']);
        }
        $fields[] = 'correo = ?';
        $types .= 's';
        $params[] = $correo;
    }
    if (array_key_exists('telefono', $body)) {
        $fields[] = 'telefono = ?';
        $types .= 's';
        $params[] = trim((string) $body['telefono']);
    }
    if (!empty($body['password'])) {
        $password = (string) $body['password'];
        if (strlen($password) < 8 || strlen($password) > 72) {
            $connection->close();
            jsonResponse(422, ['message' => 'La contraseña debe tener entre 8 y 72 caracteres.']);
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $fields[] = 'password_hash = ?';
        $types .= 's';
        $params[] = $passwordHash;
        if (tableHasColumn($connection, 'usuarios', 'password')) {
            $fields[] = 'password = ?';
            $types .= 's';
            $params[] = $passwordHash;
        }
    }

    if (!$fields) {
        $connection->close();
        jsonResponse(422, ['message' => 'No hay cambios para guardar.']);
    }

    $params[] = $userId;
    $types .= 'i';
    $statement = $connection->prepare('UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $statement->bind_param($types, ...$params);
    if (!$statement->execute()) {
        $statement->close();
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible actualizar el usuario.']);
    }
    $statement->close();
    $connection->close();
    jsonResponse(200, [
        'message' => 'Usuario actualizado correctamente.',
        'user' => ['id' => $userId, 'role' => $roleName],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    requireCsrfToken();
    $body = json_decode(file_get_contents('php://input'), true);
    $userId = (int) ($body['userId'] ?? 0);
    if ($userId < 1) {
        $connection->close();
        jsonResponse(422, ['message' => 'El usuario es obligatorio.']);
    }
    if ($userId === (int) $admin['id']) {
        $connection->close();
        jsonResponse(422, ['message' => 'No puedes eliminar tu propia cuenta.']);
    }

    $stmt = $connection->prepare('SELECT u.id, u.correo, r.nombre AS rol FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id WHERE u.id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$target) {
        $connection->close();
        jsonResponse(404, ['message' => 'El usuario no existe.']);
    }

    // No dejar el sistema sin administradores activos.
    if (mb_strtolower(trim((string) ($target['rol'] ?? ''))) === 'administrador') {
        $countResult = $connection->query("SELECT COUNT(*) AS total FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'Administrador' AND COALESCE(u.estado, 1) = 1");
        $activeAdmins = (int) (($countResult?->fetch_assoc()['total']) ?? 0);
        if ($activeAdmins <= 1) {
            $connection->close();
            jsonResponse(422, ['message' => 'No puedes desactivar al último administrador activo.']);
        }
    }

    // Baja lógica: conserva historial de pedidos, ventas y compras.
    $del = $connection->prepare('UPDATE usuarios SET estado = 0 WHERE id = ?');
    $del->bind_param('i', $userId);
    if (!$del->execute()) {
        $del->close();
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible desactivar el usuario.']);
    }
    $del->close();
    $connection->close();
    jsonResponse(200, ['message' => 'Usuario desactivado correctamente.', 'user' => ['id' => $userId, 'email' => $target['correo']]]);
}

$connection->close();
jsonResponse(405, ['message' => 'Método no permitido.']);
