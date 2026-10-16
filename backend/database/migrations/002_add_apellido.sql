-- Añade el apellido a una instalación existente sin eliminar datos.
USE liquorsoft;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre;
