<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';

$connection = databaseConnection();

$users = [
    [
        'correo' => 'admin@liquorsoft.com',
        'password' => 'Admin1234*',
        'nombre' => 'Administrador',
        'apellido' => 'LiquorSoft',
        'telefono' => '3001234567',
        'usuario' => 'admin_test',
        'rol_id' => 1,
    ],
    [
        'correo' => 'cliente@liquorsoft.com',
        'password' => 'Cliente1234*',
        'nombre' => 'Carlos',
        'apellido' => 'Cliente',
        'telefono' => '3007654321',
        'usuario' => 'cliente_test',
        'rol_id' => 2,
    ],
];

foreach ($users as $u) {
    $check = $connection->prepare('SELECT id FROM usuarios WHERE correo = ?');
    $check->bind_param('s', $u['correo']);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    if ($existing) {
        $update = $connection->prepare('UPDATE usuarios SET password_hash = ?, nombre = ?, apellido = ?, rol_id = ?, estado = 1 WHERE id = ?');
        $id = (int) $existing['id'];
        $update->bind_param('sssii', $hash, $u['nombre'], $u['apellido'], $u['rol_id'], $id);
        $update->execute();
        $update->close();
        echo "Usuario actualizado: {$u['correo']} (ID: $id)\n";
    } else {
        $insert = $connection->prepare(
            'INSERT INTO usuarios (rol_id, nombre, apellido, telefono, correo, password_hash, usuario, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $insert->bind_param('issssss', $u['rol_id'], $u['nombre'], $u['apellido'], $u['telefono'], $u['correo'], $hash, $u['usuario']);
        $insert->execute();
        $newId = $connection->insert_id;
        $insert->close();
        echo "Usuario creado: {$u['correo']} (ID: $newId)\n";
    }
}

$connection->close();
echo "Cuentas de prueba listas para testing.\n";

