-- Modelo operativo para pedidos, compras, lotes y vencimientos.
USE liquorsoft;

ALTER TABLE categorias
  ADD COLUMN IF NOT EXISTS descripcion VARCHAR(255) NULL AFTER nombre,
  ADD COLUMN IF NOT EXISTS estado TINYINT(1) NOT NULL DEFAULT 1 AFTER descripcion;

CREATE TABLE IF NOT EXISTS lotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  proveedor_id INT UNSIGNED NULL,
  codigo VARCHAR(80) NULL,
  fecha_ingreso DATE NOT NULL,
  fecha_vencimiento DATE NULL,
  cantidad_inicial INT UNSIGNED NOT NULL,
  cantidad_disponible INT UNSIGNED NOT NULL,
  CONSTRAINT fk_lotes_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT fk_lotes_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL,
  INDEX idx_lotes_vencimiento (fecha_vencimiento)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(30) NOT NULL UNIQUE,
  usuario_id INT UNSIGNED NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('PENDIENTE', 'CONFIRMADO', 'PREPARANDO', 'ENVIADO', 'ENTREGADO', 'CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  metodo_pago VARCHAR(30) NULL,
  direccion_entrega VARCHAR(255) NULL,
  notas VARCHAR(500) NULL,
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_pedidos_fecha (creado_en),
  INDEX idx_pedidos_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_pedido (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_pedido_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

ALTER TABLE ventas
  ADD COLUMN IF NOT EXISTS pedido_id INT UNSIGNED NULL AFTER cliente_id;

ALTER TABLE movimientos_inventario
  ADD COLUMN IF NOT EXISTS lote_id INT UNSIGNED NULL AFTER stock_nuevo,
  ADD COLUMN IF NOT EXISTS referencia VARCHAR(80) NULL AFTER lote_id;

CREATE TABLE IF NOT EXISTS compras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero_factura VARCHAR(80) NULL,
  proveedor_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  fecha_compra DATE NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  impuestos DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('PENDIENTE', 'RECIBIDA', 'CANCELADA') NOT NULL DEFAULT 'RECIBIDA',
  observacion VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_compras_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
  CONSTRAINT fk_compras_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS detalle_compra (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compra_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  lote_id INT UNSIGNED NULL,
  cantidad INT UNSIGNED NOT NULL,
  costo_unitario DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_detalle_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_compra_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT fk_detalle_compra_lote FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB;
