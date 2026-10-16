-- Campos aditivos para pagos de prueba, idempotencia y documentos comerciales.
USE liquorsoft;

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS clave_idempotencia CHAR(36) NULL,
  ADD COLUMN IF NOT EXISTS estado_pago VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
  ADD COLUMN IF NOT EXISTS referencia_pago VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS moneda CHAR(3) NOT NULL DEFAULT 'COP';

SET @liquorsoft_idempotency_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos'
    AND INDEX_NAME = 'uq_pedidos_idempotencia'
);
SET @liquorsoft_index_sql = IF(
  @liquorsoft_idempotency_index_exists = 0,
  'ALTER TABLE pedidos ADD UNIQUE KEY uq_pedidos_idempotencia (clave_idempotencia)',
  'SELECT 1'
);
PREPARE liquorsoft_index_statement FROM @liquorsoft_index_sql;
EXECUTE liquorsoft_index_statement;
DEALLOCATE PREPARE liquorsoft_index_statement;

ALTER TABLE detalle_pedido
  ADD COLUMN IF NOT EXISTS nombre_producto VARCHAR(150) NULL;

UPDATE detalle_pedido d
JOIN productos p ON p.id = d.producto_id
SET d.nombre_producto = p.nombre
WHERE d.nombre_producto IS NULL;
