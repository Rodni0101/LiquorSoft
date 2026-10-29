-- LiquorSoft - Catálogo comercial real (29 productos)
-- Migración idempotente: no duplica productos, no reinicia stock existente.
-- Generado desde la base de datos de producción el 2026-10-29.

SET NAMES utf8mb4;

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

-- Verificación: deben existir 29 productos activos.
SELECT COUNT(*) AS productos_activos FROM `productos` WHERE `estado` = 1;
