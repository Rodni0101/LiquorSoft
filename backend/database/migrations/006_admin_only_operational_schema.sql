-- Alinea instalaciones existentes con el modelo administrativo de LiquorSoft.
-- No crea usuarios no administrativos ni elimina el historial operativo.
USE liquorsoft;

INSERT IGNORE INTO roles (id, nombre) VALUES (1, 'Administrador'), (2, 'Cliente');

ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS sku VARCHAR(60) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS marca VARCHAR(100) NULL AFTER descripcion,
  ADD COLUMN IF NOT EXISTS presentacion VARCHAR(100) NULL AFTER marca,
  ADD COLUMN IF NOT EXISTS graduacion_alcoholica DECIMAL(5,2) NULL AFTER presentacion,
  ADD COLUMN IF NOT EXISTS precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER graduacion_alcoholica,
  ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) NULL AFTER proveedor_id;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS telefono VARCHAR(30) NULL AFTER apellido;

ALTER TABLE ventas
  ADD COLUMN IF NOT EXISTS subtotal DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER creado_en,
  ADD COLUMN IF NOT EXISTS descuento DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal,
  ADD COLUMN IF NOT EXISTS metodo_pago VARCHAR(30) NULL AFTER estado,
  ADD COLUMN IF NOT EXISTS notas VARCHAR(500) NULL AFTER metodo_pago,
  ADD COLUMN IF NOT EXISTS cliente_id INT UNSIGNED NULL AFTER usuario_id;

CREATE TABLE IF NOT EXISTS clientes (
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

ALTER TABLE ventas
  ADD CONSTRAINT fk_ventas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL;

ALTER TABLE movimientos_inventario
  ADD COLUMN IF NOT EXISTS tipo ENUM('ENTRADA', 'SALIDA', 'AJUSTE') NOT NULL DEFAULT 'AJUSTE' AFTER usuario_id,
  ADD COLUMN IF NOT EXISTS stock_anterior INT UNSIGNED NOT NULL DEFAULT 0 AFTER cantidad,
  ADD COLUMN IF NOT EXISTS stock_nuevo INT UNSIGNED NOT NULL DEFAULT 0 AFTER stock_anterior;

CREATE TABLE IF NOT EXISTS configuracion (
  clave VARCHAR(80) PRIMARY KEY,
  valor TEXT NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
