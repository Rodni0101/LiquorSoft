-- LiquorSoft - esquema canónico para MySQL 8+ / MariaDB 10.4+
-- Instalación limpia. No incluye CREATE DATABASE ni USE: selecciona la base
-- de datos del hosting en phpMyAdmin antes de importar este archivo.
-- Incluye el catálogo comercial real de 29 productos y las tablas operativas.
-- No incluye usuarios, pedidos, ventas ni datos transaccionales de producción.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `estado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identificacion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `fecha_nacimiento` date DEFAULT NULL,
  `puntos` int DEFAULT '0',
  `estado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identificacion` (`identificacion`),
  KEY `idx_cliente_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_factura` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proveedor_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `fecha_compra` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `impuestos` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('PENDIENTE','RECIBIDA','CANCELADA') COLLATE utf8mb4_unicode_ci DEFAULT 'RECIBIDA',
  `observacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_compras_proveedor` (`proveedor_id`),
  KEY `fk_compras_usuario` (`usuario_id`),
  KEY `idx_compras_fecha` (`fecha_compra`),
  CONSTRAINT `fk_compras_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `fk_compras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_compra` (
  `id` int NOT NULL AUTO_INCREMENT,
  `compra_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `lote_id` int DEFAULT NULL,
  `cantidad` int unsigned NOT NULL,
  `costo_unitario` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detalle_compra` (`compra_id`),
  KEY `fk_detalle_compra_producto` (`producto_id`),
  KEY `fk_detalle_compra_lote` (`lote_id`),
  CONSTRAINT `fk_detalle_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detalle_compra_lote` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_detalle_compra_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_pedido` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` int unsigned NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `nombre_producto` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detalle_pedido` (`pedido_id`),
  KEY `idx_detalle_pedido_producto` (`producto_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_venta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venta_id` bigint unsigned NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` int unsigned NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detalle_venta` (`venta_id`),
  KEY `idx_detalle_producto` (`producto_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `historial_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_pedido` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `estado_anterior` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metodo_entrega_anterior` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_entrega_nuevo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POR_COORDINAR',
  `detalle_entrega` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historial_pedido_fecha` (`pedido_id`,`creado_en`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lotes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` int NOT NULL,
  `proveedor_id` int DEFAULT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `cantidad_inicial` int NOT NULL,
  `cantidad_disponible` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lotes_proveedor` (`proveedor_id`),
  KEY `idx_lotes_vencimiento` (`fecha_vencimiento`),
  KEY `idx_lotes_producto` (`producto_id`),
  CONSTRAINT `fk_lotes_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_lotes_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marcas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marcas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `estado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movimientos_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos_inventario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `tipo` enum('ENTRADA','SALIDA','AJUSTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int NOT NULL,
  `stock_anterior` int NOT NULL,
  `stock_nuevo` int NOT NULL,
  `referencia` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_fecha` (`creado_en`),
  KEY `idx_mov_producto` (`producto_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `pedido_id` int DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT '0',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leida_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_usuario_leida` (`usuario_id`,`leida`,`creado_en`),
  KEY `idx_notificaciones_pedido` (`pedido_id`),
  CONSTRAINT `fk_notificaciones_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paypal_checkout_intents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paypal_checkout_intents` (
  `paypal_order_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int unsigned NOT NULL,
  `clave_idempotencia` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_cop` decimal(12,2) NOT NULL,
  `amount_usd` decimal(12,2) NOT NULL,
  `items_json` json NOT NULL,
  `direccion_entrega` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `estado` enum('CREATED','COMPLETED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CREATED',
  `capture_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`paypal_order_id`),
  UNIQUE KEY `uq_paypal_intent_usuario_idempotencia` (`usuario_id`,`clave_idempotencia`),
  KEY `idx_paypal_intent_estado` (`estado`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('PENDIENTE','CONFIRMADO','PREPARANDO','LISTO_PARA_COORDINAR','COMPLETADO','ENVIADO','ENTREGADO','CANCELADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `metodo_pago` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_entrega` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POR_COORDINAR',
  `direccion_entrega` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_idempotencia` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_pago` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `referencia_pago` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moneda` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COP',
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  UNIQUE KEY `uq_pedidos_idempotencia` (`clave_idempotencia`),
  KEY `idx_pedidos_fecha` (`creado_en`),
  KEY `idx_pedidos_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categoria_id` int NOT NULL,
  `marca_id` int NOT NULL,
  `proveedor_id` int NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_barras` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `presentacion` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contenido_ml` int DEFAULT NULL,
  `grado_alcohol` decimal(5,2) DEFAULT NULL,
  `stock` int DEFAULT '0',
  `stock_minimo` int DEFAULT '10',
  `stock_maximo` int DEFAULT '300',
  `costo` decimal(12,2) NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destacado` tinyint(1) NOT NULL DEFAULT '0',
  `estado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `fk_producto_categoria` (`categoria_id`),
  KEY `fk_producto_marca` (`marca_id`),
  KEY `fk_producto_proveedor` (`proveedor_id`),
  KEY `idx_producto_nombre` (`nombre`),
  KEY `idx_producto_codigo` (`codigo`),
  CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_producto_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_producto_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nit` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `ciudad` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pais` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rol_id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimo_login` datetime DEFAULT NULL,
  `estado` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_usuario_rol` (`rol_id`),
  KEY `idx_usuario` (`usuario`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `total` decimal(12,2) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('pendiente','completada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completada',
  `metodo_pago` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `cliente_id` int DEFAULT NULL,
  `pedido_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ventas_fecha` (`creado_en`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- Permite conciliar pagos capturados aunque la respuesta HTTP al cliente se pierda.
CREATE TABLE IF NOT EXISTS `paypal_webhook_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paypal_order_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `error` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `procesado_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paypal_webhook_event` (`event_id`),
  KEY `idx_paypal_webhook_order` (`paypal_order_id`),
  KEY `idx_paypal_webhook_processed` (`processed`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles base del sistema.
INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `estado`) VALUES
  (1, 'Administrador', 'Control total', 1),
  (2, 'Supervisor', 'Control operativo', 1),
  (3, 'Vendedor', 'Gestiona ventas', 1),
  (4, 'Bodega', 'Control inventario', 1),
  (5, 'Trabajador', 'Apoyo operativo', 1),
  (6, 'Cliente', 'Cuenta de cliente de la tienda', 1);



-- Categorías utilizadas por el catálogo
INSERT IGNORE INTO `categorias` (`nombre`, `descripcion`, `estado`) VALUES
('Cervezas', 'Cervezas nacionales e importadas', 1),
('Cremas y Licores', 'Cremas y licores dulces', 1),
('Ginebras', 'Ginebras premium', 1),
('Licores', 'Licores y destilados', 1),
('Tequilas', 'Tequilas 100% agave', 1);

-- Marcas utilizadas por el catálogo
INSERT IGNORE INTO `marcas` (`nombre`, `descripcion`, `estado`) VALUES
('Absolut', 'Vodka Absolut', 1),
('Antioqueño', 'Aguardiente antioqueño tradicional', 1),
('Bacardí', 'Ron blanco puertorriqueño', 1),
('Baileys', 'Crema de whisky Baileys', 1),
('Ballantine''s', 'Whisky escocés blended', 1),
('Beefeater', 'Ginebra Beefeater', 1),
('Buchanan''s', 'Whisky Buchanan''s', 1),
('Chivas Regal', 'Whisky escocés blended', 1),
('Club Colombia', 'Cerveza Club Colombia', 1),
('Corona', 'Cerveza lager mexicana', 1),
('Don Julio', 'Tequila Don Julio', 1),
('Gordon''s', 'Ginebra London Dry', 1),
('Grey Goose', 'Vodka Grey Goose', 1),
('Havana Club', 'Ron cubano añejo', 1),
('Heineken', 'Cerveza lager holandesa premium', 1),
('Hendrick''s', 'Ginebra Hendrick''s', 1),
('Jack Daniel''s', 'Whisky Jack Daniel''s', 1),
('Johnnie Walker', 'Whisky Johnnie Walker', 1),
('José Cuervo', 'Tequila José Cuervo', 1),
('Kahlúa', 'Licor de café Kahlúa', 1),
('Old Parr', 'Whisky Old Parr', 1),
('Patrón', 'Tequila Patrón', 1),
('Poker', 'Cerveza lager colombiana', 1),
('Smirnoff', 'Vodka Smirnoff', 1),
('Stella Artois', 'Cerveza lager belga', 1),
('Tanqueray', 'Ginebra Tanqueray', 1),
('Águila', 'Cerveza Águila', 1);

-- Proveedores de respaldo (solo si no existen)
INSERT INTO `proveedores` (`empresa`, `pais`, `estado`) SELECT 'Distribuciones LiquorSoft', 'Colombia', 1 WHERE NOT EXISTS (SELECT 1 FROM `proveedores` WHERE `empresa` = 'Distribuciones LiquorSoft');
INSERT INTO `proveedores` (`empresa`, `pais`, `estado`) SELECT 'Importadora de Licores Premium S.A.S.', 'Colombia', 1 WHERE NOT EXISTS (SELECT 1 FROM `proveedores` WHERE `empresa` = 'Importadora de Licores Premium S.A.S.');
INSERT INTO `proveedores` (`empresa`, `pais`, `estado`) SELECT 'Licores y Vinos del País S.A.S.', 'Colombia', 1 WHERE NOT EXISTS (SELECT 1 FROM `proveedores` WHERE `empresa` = 'Licores y Vinos del País S.A.S.');

CREATE TEMPORARY TABLE `tmp_catalog_2026` (
  `codigo` varchar(40) NOT NULL PRIMARY KEY,
  `codigo_barras` varchar(100) NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text NULL,
  `presentacion` varchar(60) NULL,
  `contenido_ml` int NULL,
  `grado_alcohol` decimal(5,2) NULL,
  `stock` int NULL,
  `stock_minimo` int NULL,
  `stock_maximo` int NULL,
  `costo` decimal(12,2) NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `imagen` varchar(255) NULL,
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `categoria` varchar(80) NOT NULL,
  `marca` varchar(80) NOT NULL,
  `proveedor` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_catalog_2026` VALUES
('LS-AG-001', '7702018008001', 'Aguardiente Antioqueño Clásico', 'Aguardiente antioqueño con anís, 29% vol. Tradición en cada sorbo.', 'Botella', 750, 29.00, 35, 10, 200, 35145.00, 63900.00, 'images/productos/aguardiente-antioqueno.png', 0, 'Licores', 'Antioqueño', 'Distribuciones LiquorSoft'),
('LS-CR-001', '7702018007001', 'Baileys Original Irish Cream', 'Crema de whisky irlandés, 17% vol. Cremosa con notas de cacao y vainilla.', 'Botella', 750, 17.00, 25, 6, 60, 71445.00, 129900.00, 'images/productos/baileys-irish-cream.png', 0, 'Cremas y Licores', 'Baileys', 'Licores y Vinos del País S.A.S.'),
('LS-CR-002', '7702018007002', 'Kahlúa Licor de Café', 'Licor de café mexicano, 20% vol. Intenso y aromático.', 'Botella', 750, 20.00, 22, 6, 50, 60445.00, 109900.00, 'images/productos/kahlua-licor-cafe.png', 0, 'Cremas y Licores', 'Kahlúa', 'Licores y Vinos del País S.A.S.'),
('LS-CV-001', '7702018001001', 'Cerveza Águila Original', 'Cerveza lager colombiana clásica, 4.0% vol. La cerveza de Colombia.', 'Lata', 330, 4.00, 100, 25, 300, 2365.00, 4300.00, 'images/productos/aguila-original.png', 0, 'Cervezas', 'Águila', 'Distribuciones LiquorSoft'),
('LS-CV-002', '7702018001002', 'Cerveza Águila Light', 'Cerveza light colombiana, 4.0% vol. Baja en calorías.', 'Lata', 330, 4.00, 60, 15, 150, 2695.00, 4900.00, 'images/productos/aguila-light.png', 0, 'Cervezas', 'Águila', 'Distribuciones LiquorSoft'),
('LS-CV-003', '7702018001003', 'Club Colombia Dorada', 'Cerveza lager premium colombiana, 4.7% vol. Dorada y refrescante.', 'Botella', 330, 4.70, 80, 20, 200, 3245.00, 5900.00, 'images/productos/club-colombia-dorada.png', 0, 'Cervezas', 'Club Colombia', 'Distribuciones LiquorSoft'),
('LS-CV-004', '7702018001004', 'Cerveza Poker', 'Cerveza lager colombiana, 4.4% vol. Suave y refrescante.', 'Lata', 330, 4.40, 90, 25, 300, 2310.00, 4200.00, 'images/productos/poker-cerveza.png', 0, 'Cervezas', 'Poker', 'Distribuciones LiquorSoft'),
('LS-CV-005', '7702018001005', 'Cerveza Heineken', 'Cerveza lager holandesa premium, 5.0% vol.', 'Botella', 330, 5.00, 70, 20, 180, 3575.00, 6500.00, 'images/productos/heineken-beer.png', 0, 'Cervezas', 'Heineken', 'Importadora de Licores Premium S.A.S.'),
('LS-CV-006', '7702018001006', 'Cerveza Corona Extra', 'Cerveza lager mexicana, 4.5% vol. Suave y ligera.', 'Botella', 330, 4.50, 60, 15, 150, 3740.00, 6800.00, 'images/productos/corona-extra.png', 0, 'Cervezas', 'Corona', 'Importadora de Licores Premium S.A.S.'),
('LS-CV-007', '7702018001007', 'Cerveza Stella Artois', 'Cerveza lager belga, 5.0% vol. Sabor equilibrado.', 'Botella', 330, 5.00, 50, 15, 120, 3960.00, 7200.00, 'images/productos/stella-artois.png', 0, 'Cervezas', 'Stella Artois', 'Importadora de Licores Premium S.A.S.'),
('LS-GN-001', '7702018005001', 'Tanqueray London Dry', 'Ginebra London Dry clásica, 47.3% vol. Enfática en enebro.', 'Botella', 750, 47.30, 25, 6, 50, 76945.00, 139900.00, 'images/productos/tanqueray-london-dry.png', 0, 'Ginebras', 'Tanqueray', 'Licores y Vinos del País S.A.S.'),
('LS-GN-002', '7702018005002', 'Beefeater London Dry', 'Ginebra London Dry, 40% vol. Equilibrada y versátil.', 'Botella', 750, 40.00, 28, 6, 60, 54945.00, 99900.00, 'images/productos/beefeater-london-dry.png', 0, 'Ginebras', 'Beefeater', 'Licores y Vinos del País S.A.S.'),
('LS-GN-003', '7702018005003', 'Hendrick''s Gin', 'Ginebra escocesa con pepino y rosa, 41.4% vol. Única y refrescante.', 'Botella', 750, 41.40, 12, 3, 30, 120945.00, 219900.00, 'images/productos/hendricks-gin.png', 0, 'Ginebras', 'Hendrick''s', 'Importadora de Licores Premium S.A.S.'),
('LS-GN-004', '7702018005004', 'Gordon''s London Dry', 'Ginebra London Dry, 37.5% vol. Clásica y versátil.', 'Botella', 750, 37.50, 20, 5, 50, 39600.00, 72000.00, 'images/productos/gordons-gin.png', 0, 'Ginebras', 'Gordon''s', 'Licores y Vinos del País S.A.S.'),
('LS-RN-001', '7702018002001', 'Bacardí Carta Blanca', 'Ron blanco puertorriqueño, 37.5% vol. Ideal para coctelería.', 'Botella', 750, 37.50, 29, 8, 60, 42900.00, 78000.00, 'images/productos/bacardi-carta-blanca.png', 0, 'Licores', 'Bacardí', 'Distribuciones LiquorSoft'),
('LS-RN-002', '7702018002002', 'Havana Club Añejo 3 Años', 'Ron cubano añejo 3 años, 40% vol. Tradición del Caribe.', 'Botella', 750, 40.00, 25, 6, 50, 46750.00, 85000.00, 'images/productos/havana-clan-3-anos.png', 0, 'Licores', 'Havana Club', 'Distribuciones LiquorSoft'),
('LS-TQ-001', '7702018006001', 'José Cuervo Tradicional Reposado', 'Tequila 100% agave reposado, 38% vol. Clásico para margaritas.', 'Botella', 750, 38.00, 30, 8, 60, 65945.00, 119900.00, 'images/productos/jose-cuervo-tradicional.png', 0, 'Tequilas', 'José Cuervo', 'Licores y Vinos del País S.A.S.'),
('LS-TQ-002', '7702018006002', 'Don Julio Blanco', 'Tequila 100% agave blanco, 38% vol. Fresco y herbáceo.', 'Botella', 750, 38.00, 18, 5, 45, 153945.00, 279900.00, 'images/productos/don-julio-blanco.png', 0, 'Tequilas', 'Don Julio', 'Importadora de Licores Premium S.A.S.'),
('LS-TQ-003', '7702018006003', 'Patrón Silver', 'Tequila 100% agave ultra premium blanco, 40% vol. Estándar de lujo.', 'Botella', 750, 40.00, 10, 2, 25, 197945.00, 359900.00, 'images/productos/patron-silver.png', 0, 'Tequilas', 'Patrón', 'Importadora de Licores Premium S.A.S.'),
('LS-VK-001', '7702018004001', 'Absolut Vodka', 'Vodka sueco premium, 40% vol. Puro y versátil.', 'Botella', 750, 40.00, 35, 8, 80, 71445.00, 129900.00, 'images/productos/absolut-vodka.png', 0, 'Licores', 'Absolut', 'Licores y Vinos del País S.A.S.'),
('LS-VK-002', '7702018004002', 'Smirnoff Vodka', 'Vodka clásico, 37.5% vol. Estándar mundial para coctelería.', 'Botella', 750, 37.50, 45, 10, 100, 43945.00, 79900.00, 'images/productos/smirnoff-vodka.png', 0, 'Licores', 'Smirnoff', 'Licores y Vinos del País S.A.S.'),
('LS-VK-003', '7702018004003', 'Grey Goose Vodka', 'Vodka francés ultra premium, 40% vol. Suavidad excepcional.', 'Botella', 750, 40.00, 12, 3, 30, 168935.00, 259900.00, 'images/productos/grey-goose-vodka.png', 0, 'Licores', 'Grey Goose', 'Importadora de Licores Premium S.A.S.'),
('LS-WK-001', '7702018003001', 'Old Parr 12 Años', 'Whisky escocés blended 12 años, 40% vol. Suave con notas de miel y frutos secos.', 'Botella', 750, 40.00, 20, 5, 50, 104445.00, 189900.00, 'images/productos/old-parr-12-anos.png', 0, 'Licores', 'Old Parr', 'Licores y Vinos del País S.A.S.'),
('LS-WK-002', '7702018003002', 'Buchanan''s 12 Años', 'Whisky escocés blended 12 años, 40% vol. Elegante y equilibrado.', 'Botella', 750, 40.00, 18, 5, 45, 109945.00, 199900.00, 'images/productos/buchanans-12-anos.png', 0, 'Licores', 'Buchanan''s', 'Licores y Vinos del País S.A.S.'),
('LS-WK-003', '7702018003003', 'Chivas Regal 12 Años', 'Whisky escocés blended 12 años, 40% vol. Suave y sofisticado.', 'Botella', 750, 40.00, 15, 4, 40, 101750.00, 185000.00, 'images/productos/chivas-regal-12-anos.png', 0, 'Licores', 'Chivas Regal', 'Licores y Vinos del País S.A.S.'),
('LS-WK-004', '7702018003004', 'Ballantine''s Finest', 'Whisky escocés blended, 40% vol. Equilibrado y accesible.', 'Botella', 750, 40.00, 25, 8, 60, 52250.00, 95000.00, 'images/productos/ballantines-finest.png', 0, 'Licores', 'Ballantine''s', 'Licores y Vinos del País S.A.S.'),
('LS-WK-005', '7702018003005', 'Jack Daniel''s Old No. 7', 'Whisky Tennessee, 40% vol. El clásico americano con carbón de arce.', 'Botella', 750, 40.00, 30, 8, 60, 82445.00, 149900.00, 'images/productos/jack-daniels-old-no-7.png', 1, 'Licores', 'Jack Daniel''s', 'Licores y Vinos del País S.A.S.'),
('LS-WK-006', '7702018003006', 'Johnnie Walker Red Label', 'Whisky escocés blended, 40% vol. Vibrante y versátil.', 'Botella', 750, 40.00, 28, 8, 60, 60445.00, 109900.00, 'images/productos/johnnie-walker-red-label.png', 0, 'Licores', 'Johnnie Walker', 'Licores y Vinos del País S.A.S.'),
('LS-WK-007', '7702018003007', 'Johnnie Walker Black Label 12 Años', 'Whisky escocés blended 12 años, 40% vol. Intenso y ahumado.', 'Botella', 750, 40.00, 22, 6, 50, 107745.00, 195900.00, 'images/productos/johnnie-walker-black-label.png', 0, 'Licores', 'Johnnie Walker', 'Licores y Vinos del País S.A.S.');

-- Insertar solo los productos que aún no existen por código
INSERT INTO `productos` (`categoria_id`, `marca_id`, `proveedor_id`, `codigo`, `codigo_barras`, `nombre`, `descripcion`, `presentacion`, `contenido_ml`, `grado_alcohol`, `stock`, `stock_minimo`, `stock_maximo`, `costo`, `precio`, `imagen`, `destacado`, `estado`)
SELECT c.`id`, m.`id`, pr.`id`, t.`codigo`, t.`codigo_barras`, t.`nombre`, t.`descripcion`, t.`presentacion`, t.`contenido_ml`, t.`grado_alcohol`, t.`stock`, t.`stock_minimo`, t.`stock_maximo`, t.`costo`, t.`precio`, t.`imagen`, t.`destacado`, 1
FROM `tmp_catalog_2026` t
JOIN `categorias` c ON c.`nombre` = t.`categoria`
JOIN `marcas` m ON m.`nombre` = t.`marca`
JOIN `proveedores` pr ON pr.`empresa` = t.`proveedor`
WHERE NOT EXISTS (SELECT 1 FROM `productos` p WHERE p.`codigo` = t.`codigo`);

-- Actualizar metadatos del catálogo sin tocar stock ni estado comercial
UPDATE `productos` p
JOIN `tmp_catalog_2026` t ON t.`codigo` = p.`codigo`
JOIN `categorias` c ON c.`nombre` = t.`categoria`
JOIN `marcas` m ON m.`nombre` = t.`marca`
JOIN `proveedores` pr ON pr.`empresa` = t.`proveedor`
SET p.`categoria_id` = c.`id`, p.`marca_id` = m.`id`, p.`proveedor_id` = pr.`id`,
    p.`codigo_barras` = t.`codigo_barras`, p.`nombre` = t.`nombre`, p.`descripcion` = t.`descripcion`,
    p.`presentacion` = t.`presentacion`, p.`contenido_ml` = t.`contenido_ml`, p.`grado_alcohol` = t.`grado_alcohol`,
    p.`stock_minimo` = t.`stock_minimo`, p.`stock_maximo` = t.`stock_maximo`, p.`costo` = t.`costo`,
    p.`precio` = t.`precio`, p.`imagen` = t.`imagen`, p.`destacado` = t.`destacado`;

DROP TEMPORARY TABLE `tmp_catalog_2026`;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
