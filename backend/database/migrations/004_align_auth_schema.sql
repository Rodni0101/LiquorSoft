-- Alinea una instalación existente con el contrato usado por la API de autenticación.
-- Ejecutar después de las migraciones 001-003 y revisar datos antes de asignar roles.
USE liquorsoft;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (id, nombre) VALUES (1, 'Administrador');

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS rol_id INT UNSIGNED NULL AFTER id,
  ADD COLUMN IF NOT EXISTS usuario VARCHAR(100) NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL AFTER usuario,
  ADD COLUMN IF NOT EXISTS estado TINYINT(1) NOT NULL DEFAULT 1 AFTER password,
  ADD INDEX IF NOT EXISTS idx_usuarios_rol (rol_id);

UPDATE usuarios SET rol_id = 1 WHERE rol_id IS NULL;
UPDATE usuarios SET usuario = CONCAT('usuario_', id) WHERE usuario IS NULL OR usuario = '';
UPDATE usuarios SET password = password_hash WHERE password IS NULL OR password = '';

ALTER TABLE usuarios
  MODIFY COLUMN rol_id INT UNSIGNED NOT NULL,
  MODIFY COLUMN usuario VARCHAR(100) NOT NULL,
  MODIFY COLUMN password VARCHAR(255) NOT NULL,
  ADD CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id);
