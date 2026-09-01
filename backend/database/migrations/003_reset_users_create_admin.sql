-- Operación solicitada: elimina las cuentas actuales y crea un administrador.
-- Ejecutar únicamente si se desea borrar las cuentas existentes.
USE liquorsoft;

START TRANSACTION;
DELETE FROM usuarios;

INSERT INTO usuarios
  (rol_id, nombre, apellido, correo, password_hash, usuario, password, estado)
VALUES
  (1, 'Administrador', 'LiquorSoft', 'admin123@gmail.com',
   '$2y$10$7uqvy0Ap..sDa7MXb4XBz.BlHOJ1OtEsLfMVCUZq.v4XPf7fsoRFi',
   'admin123',
   '$2y$10$7uqvy0Ap..sDa7MXb4XBz.BlHOJ1OtEsLfMVCUZq.v4XPf7fsoRFi',
   1);

COMMIT;
