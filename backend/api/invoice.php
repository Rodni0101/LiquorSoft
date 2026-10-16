<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(405, ['message' => 'Método no permitido.']);
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) jsonResponse(422, ['message' => 'Número de pedido no válido.']);
$connection = databaseConnection();
$user = requireRole($connection, ['Administrador', 'Cliente']);
$userId = (int) $user['id'];
$query = $connection->prepare(
    "SELECT p.id, p.numero, p.creado_en, p.total, p.metodo_pago, p.estado_pago, p.referencia_pago,
            p.moneda, p.direccion_entrega, u.nombre, u.apellido, u.correo
     FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id
     WHERE p.id = ? AND (p.usuario_id = ? OR ? = 1) AND p.estado_pago = 'APROBADO' LIMIT 1"
);
$isAdmin = mb_strtolower((string) ($user['rol'] ?? '')) === 'administrador' ? 1 : 0;
$query->bind_param('iii', $orderId, $userId, $isAdmin);
$query->execute();
$order = $query->get_result()->fetch_assoc();
$query->close();
if (!$order) jsonResponse(404, ['message' => 'No se encontró una compra pagada a la que tengas acceso.']);

$details = $connection->prepare(
    'SELECT COALESCE(d.nombre_producto, p.nombre) AS nombre, d.cantidad, d.precio_unitario
     FROM detalle_pedido d JOIN productos p ON p.id = d.producto_id WHERE d.pedido_id = ? ORDER BY d.id'
);
$details->bind_param('i', $orderId);
$details->execute();
$result = $details->get_result();
$items = [];
while ($row = $result->fetch_assoc()) $items[] = $row;
$details->close();
$connection->close();

$invoiceNo = 'FAC-' . (string) $order['numero'];
$buyer = trim((string) $order['nombre'] . ' ' . (string) $order['apellido']);
$rowsPerPage = 24;
$itemPages = array_chunk($items ?: [[]], $rowsPerPage);
$objects = [];
$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
$regularFontId = 3 + count($itemPages) * 2;
$boldFontId = $regularFontId + 1;
$pageRefs = [];
foreach ($itemPages as $index => $pageItems) {
    $pageId = 3 + $index * 2;
    $contentId = $pageId + 1;
    $pageRefs[] = $pageId . ' 0 R';
    $pdfString = static function (string $value): string {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii === false ? '' : $ascii);
    };
    $drawText = static function (string $value, float $x, float $y, int $size = 10, bool $bold = false) use ($pdfString): string {
        return 'BT /F' . ($bold ? '2' : '1') . ' ' . $size . ' Tf 1 0 0 1 ' . $x . ' ' . $y
            . ' Tm (' . $pdfString($value) . ") Tj ET\n";
    };
    $stream = "0.20 0.08 0.12 rg 0 720 612 122 re f\n";
    $stream .= "1 1 1 rg\n" . $drawText('LIQUORSOFT', 48, 799, 20, true);
    $stream .= $drawText('COMPROBANTE COMERCIAL DE PRUEBA', 48, 778, 9);
    $stream .= $drawText($invoiceNo, 390, 799, 13, true);
    $stream .= $drawText('Pedido ' . (string) $order['numero'] . '  |  ' . (string) $order['creado_en'], 390, 780, 8);
    $stream .= "0.20 0.08 0.12 rg\n";
    $stream .= $drawText('COMPRADOR', 48, 692, 8, true);
    $stream .= $drawText(mb_substr($buyer, 0, 54), 48, 674, 11, true);
    $stream .= $drawText(mb_substr((string) $order['correo'], 0, 58), 48, 657, 9);
    $stream .= $drawText('ENTREGA', 335, 692, 8, true);
    $stream .= $drawText(mb_substr((string) $order['direccion_entrega'], 0, 42), 335, 674, 9);
    $stream .= $drawText('Pago: ' . (string) $order['estado_pago'] . ' · ' . (string) $order['metodo_pago'], 335, 657, 8);
    $stream .= "0.94 0.91 0.90 rg 45 625 522 22 re f\n0.20 0.08 0.12 rg\n";
    $stream .= $drawText('PRODUCTO', 55, 633, 8, true);
    $stream .= $drawText('CANT.', 365, 633, 8, true);
    $stream .= $drawText('PRECIO', 420, 633, 8, true);
    $stream .= $drawText('TOTAL', 500, 633, 8, true);
    $y = 608;
    foreach ($pageItems as $item) {
        if (!$item) continue;
        $name = mb_substr((string) $item['nombre'], 0, 48);
        $quantity = (int) $item['cantidad'];
        $unit = (float) $item['precio_unitario'];
        $stream .= "0.97 0.96 0.95 rg 45 " . ($y - 6) . " 522 18 re f\n0.18 0.14 0.14 rg\n";
        $stream .= $drawText($name, 55, $y, 9);
        $stream .= $drawText((string) $quantity, 372, $y, 9);
        $stream .= $drawText(number_format($unit, 0, ',', '.'), 420, $y, 9);
        $stream .= $drawText(number_format($unit * $quantity, 0, ',', '.'), 500, $y, 9);
        $y -= 18;
    }
    if ($index === count($itemPages) - 1) {
        $stream .= "0.20 0.08 0.12 rg\n" . $drawText('Moneda: ' . (string) $order['moneda'], 335, 142, 9);
        $stream .= $drawText('TOTAL PAGADO', 335, 119, 10, true);
        $stream .= $drawText('$ ' . number_format((float) $order['total'], 0, ',', '.') . ' COP', 440, 119, 13, true);
    }
    $stream .= "0.45 0.40 0.40 rg\n" . $drawText('Referencia de prueba: ' . (string) $order['referencia_pago'], 48, 82, 8);
    $stream .= $drawText('Datos fiscales del vendedor pendientes de configuracion. No es factura electronica ni documento fiscal autorizado.', 48, 65, 7);
    $stream .= $drawText('Documento generado a partir de los datos persistidos de LiquorSoft.', 48, 51, 7);
    $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 ' . $regularFontId . ' 0 R /F2 ' . $boldFontId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
}
$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($itemPages) . ' >>';
$objects[$regularFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
$objects[$boldFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
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
for ($id = 1; $id < $size; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
$pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $invoiceNo) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
