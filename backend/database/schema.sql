-- LiquorSoft - esquema único para MySQL 8+
-- Las credenciales se configuran fuera de este archivo.
CREATE DATABASE IF NOT EXISTS liquorsoft CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE liquorsoft;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detalle_venta, ventas, alertas, productos, categorias, proveedores, usuarios;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin', 'empleado') NOT NULL DEFAULT 'empleado',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE proveedores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre_empresa VARCHAR(150) NOT NULL,
  contacto VARCHAR(100) NULL,
  telefono VARCHAR(30) NULL,
  direccion VARCHAR(200) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion VARCHAR(500) NULL,
  precio DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  categoria_id INT UNSIGNED NULL,
  proveedor_id INT UNSIGNED NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
  CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL,
  INDEX idx_productos_estado (estado),
  INDEX idx_productos_stock (stock, stock_minimo)
) ENGINE=InnoDB;

CREATE TABLE ventas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('pendiente', 'completada', 'anulada') NOT NULL DEFAULT 'completada',
  usuario_id INT UNSIGNED NULL,
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_ventas_fecha (creado_en)
) ENGINE=InnoDB;

CREATE TABLE detalle_venta (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_detalle_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
  INDEX idx_detalle_venta (venta_id)
) ENGINE=InnoDB;

CREATE TABLE alertas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  mensaje VARCHAR(250) NOT NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  generado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  producto_id INT UNSIGNED NULL,
  CONSTRAINT fk_alertas_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO categorias (nombre) VALUES ('Licores'), ('Vinos'), ('Cervezas'), ('Mixers');
INSERT INTO productos (nombre, descripcion, precio, stock, stock_minimo, destacado, categoria_id) VALUES
('Whisky Black Label', 'Suave, elegante y con notas ahumadas.', 139900, 12, 5, 1, 1),
('Ron Medellín 8 años', 'Un clásico colombiano para compartir.', 68900, 18, 5, 0, 1),
('Vino Tinto Reserva', 'Cuerpo medio con final de frutos rojos.', 54900, 9, 4, 1, 2),
('Cerveza Artesanal IPA', 'Aromática, fresca y de amargor equilibrado.', 12900, 30, 10, 0, 3),
('Tequila Reposado', 'Agave, madera y un final cálido.', 109900, 7, 4, 0, 1),
('Ginebra London Dry', 'Botánicos intensos para tu gin tonic.', 94900, 11, 4, 0, 1),
('Tónica Premium', 'Burbujas finas y cítricos sutiles.', 8900, 24, 8, 0, 4),
('Vino Rosado', 'Ligero, frutal y perfecto para una tarde.', 42900, 14, 5, 0, 2);
