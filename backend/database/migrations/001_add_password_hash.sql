-- Actualización segura para una instalación que ya tiene creada la tabla usuarios.
-- No borra usuarios ni ninguna otra tabla.
USE liquorsoft;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER correo;
