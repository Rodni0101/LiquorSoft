-- LiquorSoft - unificación de credenciales de usuarios.
-- El contrato de autenticación (backend/api/auth/Login.php) usa únicamente
-- la columna password_hash. Esta migración copia los valores existentes desde
-- la columna legacy `password` y elimina la columna redundante.

USE liquorsoft;

UPDATE usuarios
SET password_hash = password
WHERE password_hash IS NULL AND password IS NOT NULL;

ALTER TABLE usuarios
    DROP COLUMN password;

ALTER TABLE usuarios
    MODIFY COLUMN password_hash VARCHAR(255) NOT NULL;
