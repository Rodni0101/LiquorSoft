-- Estados operativos y acuerdo de entrega neutral, sin asumir domicilio o recogida.
USE liquorsoft;

INSERT IGNORE INTO roles (nombre) VALUES ('Trabajador');

ALTER TABLE pedidos
  MODIFY estado ENUM(
    'PENDIENTE', 'CONFIRMADO', 'PREPARANDO', 'LISTO_PARA_COORDINAR',
    'COMPLETADO', 'ENVIADO', 'ENTREGADO', 'CANCELADO'
  ) NOT NULL DEFAULT 'PENDIENTE';

SET @liquorsoft_fulfillment_column_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos'
    AND COLUMN_NAME = 'metodo_entrega'
);
SET @liquorsoft_fulfillment_sql = IF(
  @liquorsoft_fulfillment_column_exists = 0,
  'ALTER TABLE pedidos ADD COLUMN metodo_entrega VARCHAR(30) NOT NULL DEFAULT ''POR_COORDINAR''',
  'SELECT 1'
);
PREPARE liquorsoft_fulfillment_statement FROM @liquorsoft_fulfillment_sql;
EXECUTE liquorsoft_fulfillment_statement;
DEALLOCATE PREPARE liquorsoft_fulfillment_statement;

CREATE TABLE IF NOT EXISTS historial_pedido (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  estado_anterior VARCHAR(40) NULL,
  estado_nuevo VARCHAR(40) NOT NULL,
  metodo_entrega_anterior VARCHAR(30) NULL,
  metodo_entrega_nuevo VARCHAR(30) NOT NULL,
  detalle_entrega VARCHAR(255) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_historial_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_historial_pedido_fecha (pedido_id, creado_en)
) ENGINE=InnoDB;
