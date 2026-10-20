-- LiquorSoft - esquema administrativo para MySQL 8+
-- Este archivo crea una instalación limpia. Las contraseñas se almacenan como hashes.
CREATE DATABASE IF NOT EXISTS liquorsoft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE liquorsoft;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS paypal_checkout_intents, movimientos_inventario, detalle_venta, ventas, productos,
  clientes, proveedores, categorias, configuracion, usuarios, roles;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;
INSERT INTO roles (id, nombre) VALUES (1, 'Administrador'), (2, 'Cliente'), (3, 'Trabajador');

CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol_id INT UNSIGNED NOT NULL DEFAULT 1,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  telefono VARCHAR(30) NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  usuario VARCHAR(100) NOT NULL UNIQUE,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id),
  INDEX idx_usuarios_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE proveedores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_empresa VARCHAR(150) NOT NULL,
  identificacion VARCHAR(50) NULL UNIQUE,
  contacto VARCHAR(100) NULL,
  telefono VARCHAR(30) NULL,
  correo VARCHAR(150) NULL,
  direccion VARCHAR(200) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  documento VARCHAR(50) NULL UNIQUE,
  telefono VARCHAR(30) NULL,
  correo VARCHAR(150) NULL,
  direccion VARCHAR(200) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(60) NULL UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  descripcion VARCHAR(500) NULL,
  marca VARCHAR(100) NULL,
  presentacion VARCHAR(100) NULL,
  graduacion_alcoholica DECIMAL(5,2) NULL,
  precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0,
  precio DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  categoria_id INT UNSIGNED NULL,
  proveedor_id INT UNSIGNED NULL,
  imagen VARCHAR(255) NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
  CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL,
  INDEX idx_productos_estado (estado),
  INDEX idx_productos_stock (stock, stock_minimo),
  INDEX idx_productos_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE lotes (
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
  INDEX idx_lotes_vencimiento (fecha_vencimiento),
  INDEX idx_lotes_producto (producto_id)
) ENGINE=InnoDB;

CREATE TABLE pedidos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(30) NOT NULL UNIQUE,
  usuario_id INT UNSIGNED NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('PENDIENTE', 'CONFIRMADO', 'PREPARANDO', 'LISTO_PARA_COORDINAR', 'COMPLETADO', 'ENVIADO', 'ENTREGADO', 'CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  metodo_pago VARCHAR(30) NULL,
  metodo_entrega VARCHAR(30) NOT NULL DEFAULT 'POR_COORDINAR',
  direccion_entrega VARCHAR(255) NULL,
  notas VARCHAR(500) NULL,
  clave_idempotencia CHAR(36) NULL UNIQUE,
  estado_pago VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
  referencia_pago VARCHAR(100) NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'COP',
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_pedidos_fecha (creado_en),
  INDEX idx_pedidos_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE historial_pedido (
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

CREATE TABLE detalle_pedido (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  nombre_producto VARCHAR(150) NULL,
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_pedido_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  INDEX idx_detalle_pedido (pedido_id)
) ENGINE=InnoDB;

CREATE TABLE paypal_checkout_intents (
  paypal_order_id VARCHAR(64) PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  clave_idempotencia CHAR(36) NOT NULL,
  amount_cop DECIMAL(12,2) NOT NULL,
  amount_usd DECIMAL(12,2) NOT NULL,
  items_json JSON NOT NULL,
  direccion_entrega VARCHAR(255) NOT NULL,
  notas VARCHAR(500) NOT NULL DEFAULT '',
  estado ENUM('CREATED', 'COMPLETED') NOT NULL DEFAULT 'CREATED',
  capture_id VARCHAR(64) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_paypal_intent_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  UNIQUE KEY uq_paypal_intent_usuario_idempotencia (usuario_id, clave_idempotencia),
  INDEX idx_paypal_intent_estado (estado, creado_en)
) ENGINE=InnoDB;

CREATE TABLE ventas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('pendiente', 'completada', 'cancelada') NOT NULL DEFAULT 'completada',
  metodo_pago VARCHAR(30) NULL,
  notas VARCHAR(500) NULL,
  usuario_id INT UNSIGNED NULL,
  cliente_id INT UNSIGNED NULL,
  pedido_id INT UNSIGNED NULL,
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_ventas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
  CONSTRAINT fk_ventas_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL,
  INDEX idx_ventas_fecha (creado_en),
  INDEX idx_ventas_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE detalle_venta (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_detalle_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  INDEX idx_detalle_venta (venta_id),
  INDEX idx_detalle_producto (producto_id)
) ENGINE=InnoDB;

CREATE TABLE movimientos_inventario (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  tipo ENUM('ENTRADA', 'SALIDA', 'AJUSTE') NOT NULL,
  cantidad INT NOT NULL,
  stock_anterior INT UNSIGNED NOT NULL,
  stock_nuevo INT UNSIGNED NOT NULL,
  lote_id INT UNSIGNED NULL,
  referencia VARCHAR(80) NULL,
  motivo VARCHAR(200) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_mov_lote FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL,
  INDEX idx_mov_fecha (creado_en),
  INDEX idx_mov_producto (producto_id)
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  clave VARCHAR(80) PRIMARY KEY,
  valor TEXT NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE compras (
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
  CONSTRAINT fk_compras_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  INDEX idx_compras_fecha (fecha_compra)
) ENGINE=InnoDB;

CREATE TABLE detalle_compra (
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

CREATE TABLE IF NOT EXISTS notificaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  mensaje TEXT NOT NULL,
  pedido_id INT UNSIGNED NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  leida_en TIMESTAMP NULL,
  CONSTRAINT fk_notificaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_notificaciones_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL,
  INDEX idx_notificaciones_usuario_leida (usuario_id, leida, creado_en),
  INDEX idx_notificaciones_pedido (pedido_id)
) ENGINE=InnoDB;

INSERT INTO categorias (nombre, descripcion) VALUES
  ('Whisky', 'Destilados de cereal con crianza.'),
  ('Ron', 'Destilados de caña de azúcar.'),
  ('Vodka', 'Destilados neutros.'),
  ('Tequila', 'Destilados de agave.'),
  ('Vinos', 'Vinos tintos, blancos y rosados.'),
  ('Cervezas', 'Cervezas artesanales y comerciales.'),
  ('Gin', 'Ginebras y botánicos.'),
  ('Mixers', 'Tónicas y mezcladores.');

INSERT INTO productos (sku, nombre, descripcion, precio_compra, precio, stock, stock_minimo, destacado, categoria_id, imagen) VALUES
  ('LS-WH-001', 'Whisky Black Label', 'Johnnie Walker Black Label, blended Scotch whisky, 750 ml, 40% vol.', 98000, 139900, 12, 5, 1, 1, 'https://images.ctfassets.net/vvbow7h7clgh/QXA3D5R1hMljFQZHbxQm2/d64afef35db2c9cc0f278a584a4dbfb0/black-750ml_producthero_fullfront_desktop.webp?fm=avif&w=1900&q=60'),
  ('LS-RN-001', 'Ron Medellín 8 años', 'Ron Medellín Añejo 8 Años, ron colombiano, 750 ml, 35% ABV.', 45000, 84400, 18, 5, 0, 2, 'https://kyva.co/wp-content/uploads/2025/07/Ron-Medellin-8-Anos-750-ml_FOR-WEB_-800x1000PX.webp'),
  ('LS-VN-001', 'Vino Tinto Reserva', 'Vino Tinto Pata Negra Reserva Tempranillo, 750 ml, 13% vol.', 35000, 59990, 9, 4, 1, 5, 'https://dislicores.com/cdn/shop/files/115082-VINO-TTO-PATA-NEGRA-RESERVA-750ML-1.png?v=1775840491&width=1946'),
  ('LS-CV-001', 'Cerveza Artesanal IPA', 'Cerveza artesanal 4S tipo IPA, 330 ml, 6% ABV, envase de vidrio.', 8000, 14300, 30, 10, 0, 6, 'https://carulla.vteximg.com.br/arquivos/ids/24324771/Cerveza-4S-Cerveza-Artesanal-Medellin-Artesanal-Ipa-330-ml-3638262_a.jpg?v=639044495431070000');
