<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    jsonResponse(422, ['message' => 'Número de pedido no válido.']);
}

$connection = databaseConnection();
$user = requireRole($connection, ['Administrador', 'Cliente']);
$userId = (int) $user['id'];

$query = $connection->prepare(
    "SELECT p.id, p.numero, p.creado_en, p.total, p.metodo_pago, p.estado_pago, p.referencia_pago,
            p.moneda, p.direccion_entrega, p.notas, u.nombre, u.apellido, u.correo
     FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id
     WHERE p.id = ? AND (p.usuario_id = ? OR ? = 1) AND p.estado_pago IN ('APROBADO', 'CAPTURADO') LIMIT 1"
);
$isAdmin = mb_strtolower((string) ($user['rol'] ?? '')) === 'administrador' ? 1 : 0;
$query->bind_param('iii', $orderId, $userId, $isAdmin);
$query->execute();
$order = $query->get_result()->fetch_assoc();
$query->close();

if (!$order) {
    jsonResponse(404, ['message' => 'No se encontró una compra pagada a la que tengas acceso.']);
}

$details = $connection->prepare(
    'SELECT COALESCE(d.nombre_producto, p.nombre) AS nombre, d.cantidad, d.precio_unitario
     FROM detalle_pedido d 
     LEFT JOIN productos p ON p.id = d.producto_id 
     WHERE d.pedido_id = ? 
     ORDER BY d.id'
);
$details->bind_param('i', $orderId);
$details->execute();
$result = $details->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$details->close();
$connection->close();

$invoiceNo = 'FAC-' . (string) $order['numero'];
$buyerName = trim((string) $order['nombre'] . ' ' . (string) $order['apellido']);
if ($buyerName === '') {
    $buyerName = 'Cliente LiquorSoft';
}

$totalAmount = (float) $order['total'];
// En Colombia IVA de licores comúnmente 19%, desglose informativo para presentación profesional
$subtotalBeforeTax = round($totalAmount / 1.19, 2);
$taxAmount = round($totalAmount - $subtotalBeforeTax, 2);

$rowsPerPage = 18;
$itemPages = array_chunk($items ?: [[]], $rowsPerPage);
$objects = [];
$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

$regularFontId = 3 + count($itemPages) * 2;
$boldFontId = $regularFontId + 1;
$pageRefs = [];

// Función para codificar cadenas en WinAnsi (Windows-1252) evitando signos de interrogación en español
$encodePdfText = static function (string $text): string {
    $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    if ($converted === false) {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text) ?: $text;
    }
    // Escapar caracteres reservados de PDF literal string: \, (, )
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
};

$drawText = static function (string $text, float $x, float $y, int $size = 10, bool $bold = false) use ($encodePdfText): string {
    return 'BT /F' . ($bold ? '2' : '1') . ' ' . $size . ' Tf 1 0 0 1 ' . sprintf('%.2f', $x) . ' ' . sprintf('%.2f', $y)
        . ' Tm (' . $encodePdfText($text) . ") Tj ET\n";
};

foreach ($itemPages as $index => $pageItems) {
    $pageId = 3 + $index * 2;
    $contentId = $pageId + 1;
    $pageRefs[] = $pageId . ' 0 R';

    $stream = '';

    // 1. Barra de encabezado superior Wine Brand (#6D0F1A = 0.427, 0.059, 0.102)
    $stream .= "0.427 0.059 0.102 rg 0 715 612 127 re f\n";

    // Acento dorado en el borde inferior del encabezado (#D4AF37 = 0.831, 0.686, 0.216)
    $stream .= "0.831 0.686 0.216 rg 0 711 612 4 re f\n";

    // Textos del encabezado
    $stream .= "1 1 1 rg\n";
    $stream .= $drawText('LIQUORSOFT', 45, 804, 22, true);
    $stream .= "0.95 0.90 0.92 rg\n";
    $stream .= $drawText('SISTEMA OPERATIVO Y TIENDA ESPECIALIZADA EN LICORES', 45, 788, 8, true);
    $stream .= "0.85 0.80 0.82 rg\n";
    $stream .= $drawText('LiquorSoft Colombia S.A.S.  ·  NIT: 901.845.120-1  ·  Régimen Común', 45, 773, 8);
    $stream .= $drawText('Calle 93 # 14-20, Bogotá D.C.  ·  Tel: +57 (601) 745-8900  ·  contacto@liquorsoft.com', 45, 760, 8);

    // Caja derecha del documento
    $stream .= "1 1 1 rg\n";
    $stream .= $drawText('FACTURA ELECTRÓNICA DE VENTA', 360, 804, 11, true);
    $stream .= "0.98 0.85 0.35 rg\n"; // dorado suave
    $stream .= $drawText($invoiceNo, 360, 786, 12, true);
    $stream .= "1 1 1 rg\n";
    $stream .= $drawText('Pedido Ref: ' . (string) $order['numero'], 360, 771, 8, true);
    $stream .= "0.90 0.86 0.88 rg\n";
    $stream .= $drawText('Fecha emisión: ' . (string) $order['creado_en'], 360, 758, 8);
    $stream .= $drawText('Página ' . ($index + 1) . ' de ' . count($itemPages), 360, 745, 8);

    // 2. Paneles de Datos: Emisor (Izquierda) y Comprador (Derecha)
    // Fondo de tarjeta cliente (#FBF9FA = 0.984, 0.976, 0.980)
    $stream .= "0.984 0.976 0.980 rg 42 630 528 70 re f\n";
    // Borde de tarjeta
    $stream .= "0.88 0.82 0.84 RG 1 w 42 630 528 70 re s\n";

    // Línea divisoria central
    $stream .= "0.88 0.82 0.84 RG 1 w 306 630 0 70 re s\n";

    // Datos Comprador (Lado Izquierdo)
    $stream .= "0.427 0.059 0.102 rg\n";
    $stream .= $drawText('DATOS DEL CLIENTE', 52, 686, 8, true);
    $stream .= "0.15 0.12 0.14 rg\n";
    $stream .= $drawText(mb_substr($buyerName, 0, 38), 52, 671, 10, true);
    $stream .= "0.35 0.30 0.32 rg\n";
    $stream .= $drawText('Correo: ' . mb_substr((string) $order['correo'], 0, 36), 52, 657, 8);
    $stream .= $drawText('Entrega: ' . mb_substr((string) $order['direccion_entrega'], 0, 36), 52, 644, 8);

    // Datos de Pago y Despacho (Lado Derecho)
    $stream .= "0.427 0.059 0.102 rg\n";
    $stream .= $drawText('DETALLES DE LA TRANSACCIÓN', 316, 686, 8, true);
    $stream .= "0.15 0.12 0.14 rg\n";
    $stream .= $drawText('Método de pago: ' . strtoupper((string) $order['metodo_pago']), 316, 671, 9, true);
    $stream .= "0.35 0.30 0.32 rg\n";
    $stream .= $drawText('Estado: ' . (string) $order['estado_pago'] . '  ·  Moneda: ' . (string) $order['moneda'], 316, 657, 8);
    $refText = (string) ($order['referencia_pago'] ?: $order['numero']);
    $stream .= $drawText('Ref. Transacción: ' . mb_substr($refText, 0, 32), 316, 644, 8);

    // 3. Tabla de Productos
    // Cabecera de la tabla
    $stream .= "0.32 0.08 0.14 rg 42 602 528 20 re f\n"; // vino oscuro
    $stream .= "1 1 1 rg\n";
    $stream .= $drawText('ÍTEM', 50, 608, 8, true);
    $stream .= $drawText('DESCRIPCIÓN DEL PRODUCTO', 80, 608, 8, true);
    $stream .= $drawText('CANT.', 345, 608, 8, true);
    $stream .= $drawText('PRECIO UNITARIO', 395, 608, 8, true);
    $stream .= $drawText('TOTAL (COP)', 485, 608, 8, true);

    $y = 584;
    $rowNum = $index * $rowsPerPage + 1;
    foreach ($pageItems as $item) {
        if (!$item) continue;
        $name = mb_substr((string) $item['nombre'], 0, 42);
        $quantity = (int) $item['cantidad'];
        $unitPrice = (float) $item['precio_unitario'];
        $itemTotal = $unitPrice * $quantity;

        // Fila alterna
        if ($rowNum % 2 === 0) {
            $stream .= "0.985 0.980 0.982 rg 42 " . ($y - 5) . " 528 19 re f\n";
        } else {
            $stream .= "1 1 1 rg 42 " . ($y - 5) . " 528 19 re f\n";
        }
        // Borde inferior sutil de fila
        $stream .= "0.92 0.89 0.90 RG 0.5 w 42 " . ($y - 5) . " 528 0 re s\n";

        // Textos de fila
        $stream .= "0.45 0.40 0.42 rg\n";
        $stream .= $drawText(str_pad((string) $rowNum, 2, '0', STR_PAD_LEFT), 50, $y, 8);
        $stream .= "0.15 0.12 0.14 rg\n";
        $stream .= $drawText($name, 80, $y, 8, true);
        $stream .= "0.25 0.20 0.22 rg\n";
        $stream .= $drawText((string) $quantity, 355, $y, 8);
        $stream .= $drawText('$ ' . number_format($unitPrice, 0, ',', '.'), 395, $y, 8);
        $stream .= "0.427 0.059 0.102 rg\n";
        $stream .= $drawText('$ ' . number_format($itemTotal, 0, ',', '.'), 485, $y, 8, true);

        $y -= 19;
        $rowNum++;
    }

    // 4. Totales y Cierre (Solo en la última página)
    if ($index === count($itemPages) - 1) {
        // Caja de Totales (Lado Derecho)
        $stream .= "0.984 0.976 0.980 rg 330 " . ($y - 88) . " 240 82 re f\n";
        $stream .= "0.88 0.82 0.84 RG 1 w 330 " . ($y - 88) . " 240 82 re s\n";

        $stream .= "0.35 0.30 0.32 rg\n";
        $stream .= $drawText('Subtotal antes de IVA (19%):', 342, $y - 20, 8);
        $stream .= $drawText('$ ' . number_format($subtotalBeforeTax, 0, ',', '.') . ' COP', 472, $y - 20, 8);

        $stream .= $drawText('IVA discriminado (19%):', 342, $y - 35, 8);
        $stream .= $drawText('$ ' . number_format($taxAmount, 0, ',', '.') . ' COP', 472, $y - 35, 8);

        $stream .= $drawText('Envío y despacho:', 342, $y - 50, 8);
        $stream .= $drawText('GRATIS', 510, $y - 50, 8, true);

        // Barra del Gran Total (#6D0F1A)
        $stream .= "0.427 0.059 0.102 rg 330 " . ($y - 88) . " 240 24 re f\n";
        $stream .= "1 1 1 rg\n";
        $stream .= $drawText('TOTAL PAGADO:', 342, $y - 77, 9, true);
        $stream .= "0.98 0.85 0.35 rg\n"; // dorado
        $stream .= $drawText('$ ' . number_format($totalAmount, 0, ',', '.') . ' COP', 460, $y - 77, 11, true);

        // Mensaje y Sello de Seguridad a la izquierda de totales
        $stream .= "0.96 0.95 0.95 rg 42 " . ($y - 88) . " 275 82 re f\n";
        $stream .= "0.88 0.84 0.85 RG 1 w 42 " . ($y - 88) . " 275 82 re s\n";

        $stream .= "0.427 0.059 0.102 rg\n";
        $stream .= $drawText('CERTIFICADO DE PAGO DIGITAL', 52, $y - 20, 8, true);
        $stream .= "0.35 0.30 0.32 rg\n";
        $hashSignature = strtoupper(substr(hash('sha256', $order['numero'] . (string)$totalAmount . $order['creado_en']), 0, 32));
        $stream .= $drawText('Código de validación electrónica:', 52, $y - 34, 7);
        $stream .= "0.15 0.12 0.14 rg\n";
        $stream .= $drawText($hashSignature, 52, $y - 46, 7, true);
        $stream .= "0.35 0.30 0.32 rg\n";
        $stream .= $drawText('Transacción verificada y persistida en base de datos.', 52, $y - 62, 7);
        $stream .= $drawText('Garantía de autenticidad LiquorSoft · 100% productos originales.', 52, $y - 75, 7);
    }

    // 5. Pie de Página Legal (En todas las páginas)
    $stream .= "0.88 0.82 0.84 RG 0.5 w 42 68 528 0 re s\n";
    $stream .= "0.45 0.38 0.40 rg\n";
    $stream .= $drawText('LEY 124 DE 1994: PROHÍBASE EL EXPENDIO DE BEBIDAS EMBRIAGANTES A MENORES DE EDAD.', 45, 54, 7, true);
    $stream .= $drawText('LEY 30 DE 1986: EL EXCESO DE ALCOHOL ES PERJUDICIAL PARA LA SALUD.', 45, 43, 7, true);
    $stream .= "0.60 0.55 0.56 rg\n";
    $stream .= $drawText('Documento comercial expedido digitalmente de conformidad con el estatuto tributario colombiano. Soporte de compra.', 45, 32, 6);

    $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 ' . $regularFontId . ' 0 R /F2 ' . $boldFontId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
}

$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($itemPages) . ' >>';
$objects[$regularFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
$objects[$boldFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

ksort($objects);
$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
$offsets = [0];
foreach ($objects as $id => $object) {
    $offsets[$id] = strlen($pdf);
    $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
}
$xrefOffset = strlen($pdf);
$size = max(array_keys($objects)) + 1;
$pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
for ($id = 1; $id < $size; $id++) {
    $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
}
$pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $invoiceNo) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
