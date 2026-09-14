-- Amplía el esquema operativo: clientes, pagos, notas y movimientos.
USE liquorsoft;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  cantidad INT NOT NULL,
  motivo VARCHAR(200) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_mov_fecha (creado_en)
) ENGINE=InnoDB;
