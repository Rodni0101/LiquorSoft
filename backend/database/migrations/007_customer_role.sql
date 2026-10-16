-- Agrega el rol de cliente a instalaciones que ya ejecutaron la migración 006.
USE liquorsoft;
INSERT IGNORE INTO roles (id, nombre) VALUES (2, 'Cliente');
