-- Campos aditivos para pagos de prueba, idempotencia y documentos comerciales.
USE liquorsoft;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'clave_idempotencia') = 0,
  'ALTER TABLE pedidos ADD COLUMN clave_idempotencia CHAR(36) NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'estado_pago') = 0,
  'ALTER TABLE pedidos ADD COLUMN estado_pago VARCHAR(20) NOT NULL DEFAULT ''PENDIENTE''',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'referencia_pago') = 0,
  'ALTER TABLE pedidos ADD COLUMN referencia_pago VARCHAR(100) NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'moneda') = 0,
  'ALTER TABLE pedidos ADD COLUMN moneda CHAR(3) NOT NULL DEFAULT ''COP''',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

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

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_pedido' AND COLUMN_NAME = 'nombre_producto') = 0,
  'ALTER TABLE detalle_pedido ADD COLUMN nombre_producto VARCHAR(150) NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

UPDATE detalle_pedido d
JOIN productos p ON p.id = d.producto_id
SET d.nombre_producto = p.nombre
WHERE d.nombre_producto IS NULL;
