USE liquorsoft;

CREATE TABLE IF NOT EXISTS pedidos (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(30) NOT NULL UNIQUE,
  usuario_id INT NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM(
    'PENDIENTE', 'CONFIRMADO', 'PREPARANDO', 'LISTO_PARA_COORDINAR',
    'COMPLETADO', 'ENVIADO', 'ENTREGADO', 'CANCELADO'
  ) NOT NULL DEFAULT 'PENDIENTE',
  metodo_pago VARCHAR(30) NULL,
  metodo_entrega VARCHAR(30) NOT NULL DEFAULT 'POR_COORDINAR',
  direccion_entrega VARCHAR(255) NULL,
  notas VARCHAR(500) NULL,
  clave_idempotencia CHAR(36) NULL,
  estado_pago VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
  referencia_pago VARCHAR(100) NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'COP',
  UNIQUE KEY uq_pedidos_idempotencia (clave_idempotencia),
  INDEX idx_pedidos_fecha (creado_en),
  INDEX idx_pedidos_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_pedido (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  nombre_producto VARCHAR(150) NULL,
  INDEX idx_detalle_pedido (pedido_id),
  INDEX idx_detalle_pedido_producto (producto_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historial_pedido (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  usuario_id INT NULL,
  estado_anterior VARCHAR(40) NULL,
  estado_nuevo VARCHAR(40) NOT NULL,
  metodo_entrega_anterior VARCHAR(30) NULL,
  metodo_entrega_nuevo VARCHAR(30) NOT NULL DEFAULT 'POR_COORDINAR',
  detalle_entrega VARCHAR(255) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_historial_pedido_fecha (pedido_id, creado_en)
) ENGINE=InnoDB;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'subtotal') = 0,
  'ALTER TABLE ventas ADD COLUMN subtotal DECIMAL(12,2) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'descuento') = 0,
  'ALTER TABLE ventas ADD COLUMN descuento DECIMAL(12,2) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'estado') = 0,
  'ALTER TABLE ventas ADD COLUMN estado ENUM(''pendiente'', ''completada'', ''cancelada'') NOT NULL DEFAULT ''completada''',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'metodo_pago') = 0,
  'ALTER TABLE ventas ADD COLUMN metodo_pago VARCHAR(30) NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'notas') = 0,
  'ALTER TABLE ventas ADD COLUMN notas VARCHAR(500) NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'usuario_id') = 0,
  'ALTER TABLE ventas ADD COLUMN usuario_id INT NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'cliente_id') = 0,
  'ALTER TABLE ventas ADD COLUMN cliente_id INT NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

SET @liquorsoft_column_sql = IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ventas' AND COLUMN_NAME = 'pedido_id') = 0,
  'ALTER TABLE ventas ADD COLUMN pedido_id INT NULL',
  'SELECT 1'
);
PREPARE liquorsoft_column_statement FROM @liquorsoft_column_sql;
EXECUTE liquorsoft_column_statement;
DEALLOCATE PREPARE liquorsoft_column_statement;

CREATE TABLE IF NOT EXISTS detalle_venta (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  venta_id BIGINT UNSIGNED NOT NULL,
  producto_id INT NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  INDEX idx_detalle_venta (venta_id),
  INDEX idx_detalle_producto (producto_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  producto_id INT NOT NULL,
  usuario_id INT NULL,
  tipo ENUM('ENTRADA', 'SALIDA', 'AJUSTE') NOT NULL,
  cantidad INT NOT NULL,
  stock_anterior INT NOT NULL,
  stock_nuevo INT NOT NULL,
  referencia VARCHAR(80) NULL,
  motivo VARCHAR(200) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mov_fecha (creado_en),
  INDEX idx_mov_producto (producto_id)
) ENGINE=InnoDB;
