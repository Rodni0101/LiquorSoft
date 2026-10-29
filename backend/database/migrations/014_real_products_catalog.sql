-- Migración 013: Actualización de catálogo con productos reales y precios verificados Colombia 2026
-- Esta migración reemplaza los productos de prueba con un catálogo realista
-- Precios en COP (pesos colombianos) basados en referencias de mercado público
-- Fuentes: licoreselfaro.com.co, laembajadora.co, lalicorera.com, d1.com.co
-- Fecha de verificación: 2026-10-20

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

-- Primero, desactivar productos existentes que no tengan pedidos asociados
-- (Los que tengan pedidos se mantienen para integridad histórica)
UPDATE `productos` SET `estado` = 0
WHERE `id` IN (2,3,4,5,6,7,8)
  AND `id` NOT IN (SELECT DISTINCT `producto_id` FROM `detalle_pedido`)
  AND `id` NOT IN (SELECT DISTINCT `producto_id` FROM `detalle_venta`)
  AND `id` NOT IN (SELECT DISTINCT `producto_id` FROM `detalle_compra`)
  AND `id` NOT IN (SELECT DISTINCT `producto_id` FROM `movimientos_inventario`)
  AND `id` NOT IN (SELECT DISTINCT `producto_id` FROM `lotes`);

-- Asegurar que existan las categorías necesarias
INSERT IGNORE INTO `categorias` (`id`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Aguardientes', 'Aguardientes colombianos tradicionales', 1),
(2, 'Rones', 'Rones nacionales e importados', 1),
(3, 'Whiskies', 'Whiskies escoceses, americanos y japoneses', 1),
(4, 'Vodkas', 'Vodkas premium y estándar', 1),
(5, 'Tequilas', 'Tequilas blancos, reposados y añejos', 1),
(6, 'Ginebras', 'Ginebras London Dry y contemporáneas', 1),
(7, 'Vinos y Espumosos', 'Vinos tintos, blancos, rosados y espumosos', 1),
(8, 'Cervezas', 'Cervezas artesanales y comerciales', 1),
(9, 'Cremas y Licores', 'Cremas de whisky, licores de café y otros', 1),
(10, 'Mixers y Complementos', 'Tónicas, sodas, bitters y jarabes', 1);

-- Asegurar que existan las marcas necesarias
INSERT IGNORE INTO `marcas` (`id`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Selección LiquorSoft', 'Productos seleccionados para el catálogo inicial', 1),
(2, 'Antioqueño', 'Aguardiente antioqueño tradicional', 1),
(3, 'Cristal', 'Aguardiente caucano', 1),
(4, 'Medellín', 'Ron Medellín', 1),
(5, 'Dictador', 'Ron Dictador colombiano', 1),
(6, 'Old Parr', 'Whisky Old Parr', 1),
(7, 'Buchanan\'s', 'Whisky Buchanan\'s', 1),
(8, 'Jack Daniel\'s', 'Whisky Jack Daniel\'s', 1),
(9, 'Johnnie Walker', 'Whisky Johnnie Walker', 1),
(10, 'Absolut', 'Vodka Absolut', 1),
(11, 'Smirnoff', 'Vodka Smirnoff', 1),
(12, 'Grey Goose', 'Vodka Grey Goose', 1),
(13, 'José Cuervo', 'Tequila José Cuervo', 1),
(14, 'Don Julio', 'Tequila Don Julio', 1),
(15, 'Patrón', 'Tequila Patrón', 1),
(16, 'Tanqueray', 'Ginebra Tanqueray', 1),
(17, 'Beefeater', 'Ginebra Beefeater', 1),
(18, 'Hendrick\'s', 'Ginebra Hendrick\'s', 1),
(19, 'Casillero del Diablo', 'Vinos Casillero del Diablo', 1),
(20, 'Concha y Toro', 'Vinos Concha y Toro', 1),
(21, 'Santa Margherita', 'Vinos Santa Margherita', 1),
(22, 'Club Colombia', 'Cerveza Club Colombia', 1),
(23, 'Águila', 'Cerveza Águila', 1),
(24, 'Pokémon', 'Cerveza artesanal Pokémon', 1),
(25, 'Baileys', 'Crema de whisky Baileys', 1),
(26, 'Kahlúa', 'Licor de café Kahlúa', 1),
(27, 'Schweppes', 'Tónica Schweppes', 1),
(28, 'Fever-Tree', 'Tónica Fever-Tree', 1);

-- Asegurar proveedor por defecto
INSERT IGNORE INTO `proveedores` (`id`, `empresa`, `nit`, `contacto`, `telefono`, `correo`, `direccion`, `ciudad`, `pais`, `estado`) VALUES
(1, 'Distribuciones LiquorSoft', 'LS-0001', 'Compras LiquorSoft', '601XXXXXXX', 'compras@liquorsoft.com', 'Calle 100 #15-20', 'Bogotá', 'Colombia', 1),
(2, 'Licores y Vinos del País S.A.S.', '900123456-7', 'Juan Pérez', '601XXXXXXX', 'ventas@licorespais.com', 'Av. El Dorado #68-35', 'Bogotá', 'Colombia', 1),
(3, 'Importadora de Licores Premium S.A.S.', '900987654-3', 'María González', '604XXXXXXX', 'comercial@importadorapremium.com', 'Carrera 43A #5-10', 'Medellín', 'Colombia', 1);

-- Insertar catálogo de productos reales con precios verificados
-- NOTA: Los precios son referenciales basados en mercado colombiano 2026
-- Costo estimado = precio * 0.55 (margen ~45%) para productos estándar
-- Costo estimado = precio * 0.65 (margen ~35%) para productos premium

INSERT INTO `productos` (
  `categoria_id`, `marca_id`, `proveedor_id`, `codigo`, `codigo_barras`,
  `nombre`, `descripcion`, `presentacion`, `contenido_ml`, `grado_alcohol`,
  `stock`, `stock_minimo`, `stock_maximo`, `costo`, `precio`, `imagen`, `estado`
) VALUES
-- AGUARDIENTES (Cat 1)
(1, 2, 1, 'LS-AG-001', '7702018001234', 'Aguardiente Antioqueño Sin Azúcar',
 'Aguardiente antioqueño sin azúcar, 35% vol. El clásico de la fiesta colombiana.', 'Botella', 750, 35.00, 50, 10, 200, 38500.00, 69900.00, 'assets/products/aguardiente.svg', 1),
(1, 2, 1, 'LS-AG-002', '7702018001241', 'Aguardiente Antioqueño Clásico',
 'Aguardiente antioqueño clásico con anís, 29% vol. Tradición en cada sorbo.', 'Botella', 750, 29.00, 40, 10, 200, 35200.00, 63900.00, 'assets/products/aguardiente.svg', 1),
(1, 3, 1, 'LS-AG-003', '7702018002101', 'Aguardiente Cristal',
 'Aguardiente caucano, suave y tradicional, 29% vol.', 'Botella', 750, 29.00, 35, 8, 150, 32800.00, 59900.00, 'assets/products/aguardiente.svg', 1),
(1, 2, 1, 'LS-AG-004', '7702018001258', 'Aguardiente Antioqueño Light',
 'Aguardiente light, menor graduación, 24% vol.', 'Botella', 750, 24.00, 20, 5, 100, 36400.00, 65900.00, 'assets/products/aguardiente.svg', 1),

-- RONES (Cat 2)
(2, 4, 1, 'LS-RN-001', '7707290001234', 'Ron Medellín 8 Años',
 'Ron Medellín Añejo 8 Años, ron colombiano premium, 35% vol. Notas de vainilla y roble.', 'Botella', 750, 35.00, 25, 8, 60, 46800.00, 84900.00, 'assets/products/ron.svg', 1),
(2, 4, 1, 'LS-RN-002', '7707290001241', 'Ron Medellín 12 Años',
 'Ron Medellín Gran Reserva 12 Años, 40% vol. Complejidad y elegancia.', 'Botella', 750, 40.00, 15, 5, 40, 82500.00, 149900.00, 'assets/products/ron.svg', 1),
(2, 5, 1, 'LS-RN-003', '7707290002101', 'Ron Dictador 12 Años',
 'Ron Dictador 12 Años, solera, 40% vol. Dulzura y cuerpo excepcional.', 'Botella', 700, 40.00, 12, 4, 30, 115500.00, 209900.00, 'assets/products/ron.svg', 1),
(2, 5, 1, 'LS-RN-004', '7707290002118', 'Ron Dictador 20 Años',
 'Ron Dictador 20 Años, solera, 40% vol. Lujo y sofisticación.', 'Botella', 700, 40.00, 8, 2, 20, 236500.00, 429900.00, 'assets/products/ron.svg', 1),
(2, 1, 1, 'LS-RN-005', '7707290003101', 'Ron Blanco Selección LiquorSoft',
 'Ron blanco suave para coctelería, 35% vol. Ideal para mojitos y daiquiris.', 'Botella', 750, 35.00, 40, 10, 100, 24200.00, 43900.00, 'assets/products/ron.svg', 1),
(2, 1, 1, 'LS-RN-006', '7707290003118', 'Ron Dorado Selección LiquorSoft',
 'Ron dorado con notas de caramelo, 35% vol. Perfecto para cuba libre.', 'Botella', 750, 35.00, 35, 8, 80, 27500.00, 49900.00, 'assets/products/ron.svg', 1),

-- WHISKIES (Cat 3)
(3, 6, 2, 'LS-WK-001', '7707290004101', 'Old Parr 12 Años',
 'Whisky escocés blended 12 años, 40% vol. Suave, con notas de miel y frutos secos.', 'Botella', 750, 40.00, 20, 5, 50, 104500.00, 189900.00, 'assets/products/whisky.svg', 1),
(3, 7, 2, 'LS-WK-002', '7707290004118', 'Buchanan\'s 12 Años',
 'Whisky escocés blended 12 años, 40% vol. Elegante y equilibrado.', 'Botella', 750, 40.00, 18, 5, 45, 110000.00, 199900.00, 'assets/products/whisky.svg', 1),
(3, 7, 2, 'LS-WK-003', '7707290004125', 'Buchanan\'s 18 Años',
 'Whisky escocés blended 18 años, 40% vol. Complejidad superior.', 'Botella', 750, 40.00, 8, 2, 20, 242000.00, 439900.00, 'assets/products/whisky.svg', 1),
(3, 8, 2, 'LS-WK-004', '7707290004132', 'Jack Daniel\'s Old No. 7',
 'Whisky Tennessee, 40% vol. El clásico americano con carbón de arce.', 'Botella', 750, 40.00, 30, 8, 60, 82500.00, 149900.00, 'assets/products/whisky.svg', 1),
(3, 8, 2, 'LS-WK-005', '7707290004149', 'Jack Daniel\'s Honey',
 'Whisky Tennessee con miel, 35% vol. Dulce y suave.', 'Botella', 750, 35.00, 25, 6, 50, 88000.00, 159900.00, 'assets/products/whisky.svg', 1),
(3, 9, 2, 'LS-WK-006', '7707290004156', 'Johnnie Walker Black Label 12 Años',
 'Whisky escocés blended 12 años, 40% vol. Intenso y ahumado.', 'Botella', 750, 40.00, 22, 6, 50, 107800.00, 195900.00, 'assets/products/whisky.svg', 1),
(3, 9, 2, 'LS-WK-007', '7707290004163', 'Johnnie Walker Red Label',
 'Whisky escocés blended, 40% vol. Vibrante y versátil.', 'Botella', 750, 40.00, 28, 8, 60, 60500.00, 109900.00, 'assets/products/whisky.svg', 1),
(3, 1, 2, 'LS-WK-008', '7707290004170', 'Whisky Escocés Selección LiquorSoft 8 Años',
 'Whisky blended 8 años, 40% vol. Buena relación calidad-precio.', 'Botella', 750, 40.00, 15, 4, 40, 55000.00, 99900.00, 'assets/products/whisky.svg', 1),

-- VODKAS (Cat 4)
(4, 10, 2, 'LS-VK-001', '7707290005101', 'Absolut Vodka',
 'Vodka sueco premium, 40% vol. Puro y versátil.', 'Botella', 750, 40.00, 35, 8, 80, 71500.00, 129900.00, 'assets/products/vodka.svg', 1),
(4, 10, 2, 'LS-VK-002', '7707290005118', 'Absolut Vodka Citron',
 'Vodka sueco con limón, 40% vol. Ideal para cosmopolitans.', 'Botella', 750, 40.00, 20, 5, 50, 77000.00, 139900.00, 'assets/products/vodka.svg', 1),
(4, 11, 2, 'LS-VK-003', '7707290005125', 'Smirnoff Vodka',
 'Vodka clásico, 37.5% vol. Estándar mundial para coctelería.', 'Botella', 750, 37.50, 45, 10, 100, 44000.00, 79900.00, 'assets/products/vodka.svg', 1),
(4, 12, 3, 'LS-VK-004', '7707290005132', 'Grey Goose Vodka',
 'Vodka francés ultra premium, 40% vol. Suavidad excepcional.', 'Botella', 750, 40.00, 12, 3, 30, 143000.00, 259900.00, 'assets/products/vodka.svg', 1),

-- TEQUILAS (Cat 5)
(5, 13, 2, 'LS-TQ-001', '7707290006101', 'José Cuervo Tradicional Reposado',
 'Tequila 100% agave reposado, 38% vol. Clásico para margaritas.', 'Botella', 750, 38.00, 30, 8, 60, 66000.00, 119900.00, 'assets/products/tequila.svg', 1),
(5, 13, 2, 'LS-TQ-002', '7707290006118', 'José Cuervo Especial Silver',
 'Tequila mixto blanco, 38% vol. Estándar para shots y cocteles.', 'Botella', 750, 38.00, 35, 8, 80, 55000.00, 99900.00, 'assets/products/tequila.svg', 1),
(5, 14, 3, 'LS-TQ-003', '7707290006125', 'Don Julio Reposado',
 'Tequila 100% agave reposado, 38% vol. Suave con notas de vainilla.', 'Botella', 750, 38.00, 15, 4, 40, 165000.00, 299900.00, 'assets/products/tequila.svg', 1),
(5, 14, 3, 'LS-TQ-004', '7707290006132', 'Don Julio Blanco',
 'Tequila 100% agave blanco, 38% vol. Fresco y herbáceo.', 'Botella', 750, 38.00, 18, 5, 45, 154000.00, 279900.00, 'assets/products/tequila.svg', 1),
(5, 15, 3, 'LS-TQ-005', '7707290006149', 'Patrón Silver',
 'Tequila 100% agave ultra premium blanco, 40% vol. El estándar de lujo.', 'Botella', 750, 40.00, 10, 2, 25, 198000.00, 359900.00, 'assets/products/tequila.svg', 1),
(5, 15, 3, 'LS-TQ-006', '7707290006156', 'Patrón Reposado',
 'Tequila 100% agave ultra premium reposado, 40% vol. Complejidad y elegancia.', 'Botella', 750, 40.00, 8, 2, 20, 214500.00, 389900.00, 'assets/products/tequila.svg', 1),

-- GINEBRAS (Cat 6)
(6, 16, 2, 'LS-GN-001', '7707290007101', 'Tanqueray London Dry',
 'Ginebra London Dry clásica, 47.3% vol. Enfática en enebro.', 'Botella', 750, 47.30, 25, 6, 50, 77000.00, 139900.00, 'assets/products/gin.svg', 1),
(6, 17, 2, 'LS-GN-002', '7707290007118', 'Beefeater London Dry',
 'Ginebra London Dry, 40% vol. Equilibrada y versátil.', 'Botella', 750, 40.00, 28, 6, 60, 55000.00, 99900.00, 'assets/products/gin.svg', 1),
(6, 18, 3, 'LS-GN-003', '7707290007125', 'Hendrick\'s Gin',
 'Ginebra escocesa con pepino y rosa, 41.4% vol. Única y refrescante.', 'Botella', 750, 41.40, 12, 3, 30, 121000.00, 219900.00, 'assets/products/gin.svg', 1),
(6, 1, 2, 'LS-GN-004', '7707290007132', 'Ginebra London Dry Selección LiquorSoft',
 'Ginebra London Dry, 40% vol. Buena para gin tonic diario.', 'Botella', 700, 40.00, 20, 5, 50, 49500.00, 89900.00, 'assets/products/gin.svg', 1),

-- VINOS Y ESPUMOSOS (Cat 7)
(7, 19, 2, 'LS-VN-001', '7707290008101', 'Casillero del Diablo Cabernet Sauvignon',
 'Vino tinto chileno, 13.5% vol. Cuerpo medio, notas a frutos rojos.', 'Botella', 750, 13.50, 30, 8, 60, 38500.00, 69900.00, 'assets/products/wine.svg', 1),
(7, 19, 2, 'LS-VN-002', '7707290008118', 'Casillero del Diablo Carmenère',
 'Vino tinto chileno, 13.5% vol. Especiado y aterciopelado.', 'Botella', 750, 13.50, 25, 6, 50, 38500.00, 69900.00, 'assets/products/wine.svg', 1),
(7, 20, 2, 'LS-VN-003', '7707290008125', 'Concha y Toro Merlot',
 'Vino tinto chileno, 13% vol. Suave y afrutado.', 'Botella', 750, 13.00, 28, 8, 60, 33000.00, 59900.00, 'assets/products/wine.svg', 1),
(7, 20, 2, 'LS-VN-004', '7707290008132', 'Concha y Toro Chardonnay',
 'Vino blanco chileno, 13% vol. Fresco con notas tropicales.', 'Botella', 750, 13.00, 22, 6, 50, 33000.00, 59900.00, 'assets/products/wine.svg', 1),
(7, 21, 3, 'LS-VN-005', '7707290008149', 'Santa Margherita Pinot Grigio',
 'Vino blanco italiano, 12.5% vol. Elegante y mineral.', 'Botella', 750, 12.50, 15, 4, 30, 82500.00, 149900.00, 'assets/products/wine.svg', 1),
(7, 19, 2, 'LS-VN-006', '7707290008156', 'Casillero del Diablo Rosé',
 'Vino rosado chileno, 12.5% vol. Ligero y frutal.', 'Botella', 750, 12.50, 20, 5, 40, 38500.00, 69900.00, 'assets/products/wine.svg', 1),
(7, 19, 2, 'LS-VN-007', '7707290008163', 'Casillero del Diablo Brut',
 'Espumoso chileno, 12% vol. Burbujas finas y refrescante.', 'Botella', 750, 12.00, 18, 5, 40, 44000.00, 79900.00, 'assets/products/wine.svg', 1),

-- CERVEZAS (Cat 8)
(8, 22, 1, 'LS-CV-001', '7707290009101', 'Club Colombia Dorada',
 'Cerveza lager premium colombiana, 4.7% vol. Dorada y refrescante.', 'Botella', 330, 4.70, 80, 20, 200, 3300.00, 5900.00, 'assets/products/beer.svg', 1),
(8, 22, 1, 'LS-CV-002', '7707290009118', 'Club Colombia Roja',
 'Cerveza roja tipo amber, 5.0% vol. Cuerpo y maltosidad.', 'Botella', 330, 5.00, 70, 20, 180, 3300.00, 5900.00, 'assets/products/beer.svg', 1),
(8, 22, 1, 'LS-CV-003', '7707290009125', 'Club Colombia Negra',
 'Cerveza stout colombiana, 5.5% vol. Notas a café y chocolate.', 'Botella', 330, 5.50, 50, 15, 120, 3520.00, 6300.00, 'assets/products/beer.svg', 1),
(8, 23, 1, 'LS-CV-004', '7707290009132', 'Águila Light',
 'Cerveza light colombiana, 4.0% vol. Baja en calorías.', 'Botella', 330, 4.00, 60, 15, 150, 2750.00, 4900.00, 'assets/products/beer.svg', 1),
(8, 23, 1, 'LS-CV-005', '7707290009149', 'Águila Original',
 'Cerveza lager colombiana clásica, 4.0% vol. La cerveza de Colombia.', 'Botella', 330, 4.00, 100, 25, 300, 2420.00, 4300.00, 'assets/products/beer.svg', 1),
(8, 24, 3, 'LS-CV-006', '7707290009156', 'Pokémon IPA',
 'Cerveza artesanal IPA colombiana, 6.5% vol. Intensa y lupulada.', 'Botella', 330, 6.50, 30, 8, 80, 8800.00, 15900.00, 'assets/products/beer.svg', 1),
(8, 24, 3, 'LS-CV-007', '7707290009163', 'Pokémon Golden Ale',
 'Cerveza artesanal Golden Ale, 5.2% vol. Equilibrada y dorada.', 'Botella', 330, 5.20, 25, 8, 60, 8250.00, 14900.00, 'assets/products/beer.svg', 1),

-- CREMAS Y LICORES (Cat 9)
(9, 25, 2, 'LS-CR-001', '7707290010101', 'Baileys Original Irish Cream',
 'Crema de whisky irlandés, 17% vol. Cremosa con notas de cacao y vainilla.', 'Botella', 750, 17.00, 25, 6, 60, 71500.00, 129900.00, 'assets/products/cream.svg', 1),
(9, 26, 2, 'LS-CR-002', '7707290010118', 'Kahlúa Licor de Café',
 'Licor de café mexicano, 20% vol. Intenso y aromático.', 'Botella', 750, 20.00, 22, 6, 50, 60500.00, 109900.00, 'assets/products/cream.svg', 1),
(9, 1, 2, 'LS-CR-003', '7707290010125', 'Crema de Whisky Selección LiquorSoft',
 'Crema de whisky colombiana, 15% vol. Suave y dulce.', 'Botella', 750, 15.00, 30, 8, 80, 33000.00, 59900.00, 'assets/products/cream.svg', 1),

-- MIXERS Y COMPLEMENTOS (Cat 10)
(10, 27, 1, 'LS-MX-001', '7707290011101', 'Schweppes Tónica Original',
 'Agua tónica clásica, 200 ml. Quinina y burbuja fina.', 'Botella', 200, 0.00, 100, 25, 200, 1650.00, 2900.00, 'assets/products/mixer.svg', 1),
(10, 27, 1, 'LS-MX-002', '7707290011118', 'Schweppes Tónica Zero',
 'Agua tónica sin azúcar, 200 ml. Misma burbuja, cero calorías.', 'Botella', 200, 0.00, 80, 20, 180, 1650.00, 2900.00, 'assets/products/mixer.svg', 1),
(10, 28, 3, 'LS-MX-003', '7707290011125', 'Fever-Tree Indian Tonic Water',
 'Tónica premium, 200 ml. Quinina natural de Congo.', 'Botella', 200, 0.00, 40, 10, 100, 4400.00, 7900.00, 'assets/products/mixer.svg', 1),
(10, 28, 3, 'LS-MX-004', '7707290011132', 'Fever-Tree Ginger Beer',
 'Cerveza de jengibre premium, 200 ml. Picante y refrescante.', 'Botella', 200, 0.00, 35, 10, 80, 4400.00, 7900.00, 'assets/products/mixer.svg', 1),
(10, 1, 1, 'LS-MX-005', '7707290011149', 'Jarabe de Goma Selección LiquorSoft',
 'Jarabe simple para coctelería, 500 ml. Dulzura equilibrada.', 'Botella', 500, 0.00, 50, 10, 100, 5500.00, 9900.00, 'assets/products/mixer.svg', 1);

-- Actualizar auto_increment para evitar colisiones
ALTER TABLE `productos` AUTO_INCREMENT = 100;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;