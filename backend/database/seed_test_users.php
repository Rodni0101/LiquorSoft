<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';

$connection = databaseConnection();

function roleIdByName(mysqli $connection, string $name): int
{
    $statement = $connection->prepare('SELECT id FROM roles WHERE nombre = ? LIMIT 1');
    $statement->bind_param('s', $name);
    $statement->execute();
    $role = $statement->get_result()->fetch_assoc();
    $statement->close();
    if (!$role) {
        fwrite(STDERR, "No existe el rol '{$name}'. Ejecuta las migraciones antes de crear los usuarios de prueba.\n");
        exit(1);
    }
    return (int) $role['id'];
}

$users = [
    [
        'correo' => 'admin@liquorsoft.com',
        'password' => 'Admin1234*',
        'nombre' => 'Administrador',
        'apellido' => 'LiquorSoft',
        'telefono' => '3001234567',
        'usuario' => 'admin_test',
        'rol_id' => roleIdByName($connection, 'Administrador'),
    ],
    [
        'correo' => 'cliente@liquorsoft.com',
        'password' => 'Cliente1234*',
        'nombre' => 'Carlos',
        'apellido' => 'Cliente',
        'telefono' => '3007654321',
        'usuario' => 'cliente_test',
        'rol_id' => roleIdByName($connection, 'Cliente'),
    ],
];

$legacyPasswordColumn = tableHasColumn($connection, 'usuarios', 'password');

foreach ($users as $u) {
    $check = $connection->prepare('SELECT id FROM usuarios WHERE correo = ?');
    $check->bind_param('s', $u['correo']);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    if ($existing) {
        $id = (int) $existing['id'];
        if ($legacyPasswordColumn) {
            $update = $connection->prepare('UPDATE usuarios SET password_hash = ?, password = ?, nombre = ?, apellido = ?, rol_id = ?, estado = 1 WHERE id = ?');
            $update->bind_param('ssssii', $hash, $hash, $u['nombre'], $u['apellido'], $u['rol_id'], $id);
        } else {
            $update = $connection->prepare('UPDATE usuarios SET password_hash = ?, nombre = ?, apellido = ?, rol_id = ?, estado = 1 WHERE id = ?');
            $update->bind_param('sssii', $hash, $u['nombre'], $u['apellido'], $u['rol_id'], $id);
        }
        if (!$update->execute()) {
            fwrite(STDERR, "No fue posible actualizar {$u['correo']}: {$update->error}\n");
            exit(1);
        }
        $update->close();
        echo "Usuario actualizado: {$u['correo']} (ID: $id)\n";
    } else {
        if ($legacyPasswordColumn) {
            $insert = $connection->prepare(
                'INSERT INTO usuarios (rol_id, nombre, apellido, telefono, correo, password_hash, password, usuario, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $insert->bind_param('isssssss', $u['rol_id'], $u['nombre'], $u['apellido'], $u['telefono'], $u['correo'], $hash, $hash, $u['usuario']);
        } else {
            $insert = $connection->prepare(
                'INSERT INTO usuarios (rol_id, nombre, apellido, telefono, correo, password_hash, usuario, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $insert->bind_param('issssss', $u['rol_id'], $u['nombre'], $u['apellido'], $u['telefono'], $u['correo'], $hash, $u['usuario']);
        }
        if (!$insert->execute()) {
            fwrite(STDERR, "No fue posible crear {$u['correo']}: {$insert->error}\n");
            exit(1);
        }
        $newId = $connection->insert_id;
        $insert->close();
        echo "Usuario creado: {$u['correo']} (ID: $newId)\n";
    }
}

$connection->close();
echo "Cuentas de prueba listas para testing.\n";

