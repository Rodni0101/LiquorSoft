<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/paypal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['message' => 'Método no permitido.']);
}
requireCsrfToken();

$configuration = paypalConfiguration();
if (!$configuration['enabled']) {
    jsonResponse(503, ['message' => 'PayPal no está habilitado. Revisa las credenciales y la tasa COP/USD del servidor.']);
}
$payload = json_decode(file_get_contents('php://input'), true);
$items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
$key = strtolower(trim((string) ($payload['idempotencyKey'] ?? '')));
$address = trim((string) ($payload['deliveryAddress'] ?? ''));
$birthDate = trim((string) ($payload['birthDate'] ?? ''));
$notes = trim((string) ($payload['notes'] ?? ''));
if (!$items || count($items) > 40 || strlen($key) !== 36 || !preg_match('/^[0-9a-f-]{36}$/', $key)) {
    jsonResponse(422, ['message' => 'El carrito o la clave de compra no son válidos.']);
}
if ($address === '' || mb_strlen($address) > 255 || mb_strlen($notes) > 500) {
    jsonResponse(422, ['message' => 'La dirección de entrega o las notas no son válidas.']);
}
if (!isAdultBirthDate($birthDate)) {
    jsonResponse(422, ['message' => 'Debes declarar una fecha de nacimiento que confirme que tienes 18 años o más.']);
}

$normalizedItems = [];
foreach ($items as $item) {
    $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
    if (!$productId || !$quantity || $quantity < 1 || $quantity > 100 || isset($normalizedItems[$productId])) {
        jsonResponse(422, ['message' => 'El carrito contiene cantidades inválidas.']);
    }
    $normalizedItems[$productId] = $quantity;
}
ksort($normalizedItems, SORT_NUMERIC);
$itemsJson = json_encode(
    array_map(static fn (int $id, int $quantity): array => ['id' => $id, 'quantity' => $quantity], array_keys($normalizedItems), array_values($normalizedItems)),
    JSON_THROW_ON_ERROR
);

$connection = databaseConnection();
$actor = requireRole($connection, ['Administrador', 'Cliente']);
$userId = (int) $actor['id'];
try {
    // 1. Leer/crear la intención local con una transacción corta (sin llamadas externas).
    $connection->begin_transaction();
    $existingQuery = $connection->prepare(
        'SELECT paypal_order_id, items_json, direccion_entrega, notas, amount_usd, estado
         FROM paypal_checkout_intents WHERE usuario_id = ? AND clave_idempotencia = ? LIMIT 1'
    );
    $existingQuery->bind_param('is', $userId, $key);
    $existingQuery->execute();
    $existing = $existingQuery->get_result()->fetch_assoc();
    $existingQuery->close();
    if ($existing) {
        if ($existing['items_json'] !== $itemsJson || $existing['estado'] !== 'CREATED') {
            throw new DomainException('Esta clave de compra ya fue usada para otros datos.');
        }
        $connection->commit();
        $connection->close();
        jsonResponse(200, [
            'orderId' => (string) $existing['paypal_order_id'],
            'currency' => 'USD',
            'amount' => (string) $existing['amount_usd'],
        ]);
    }

    $totalCents = 0;
    foreach ($normalizedItems as $productId => $quantity) {
        $productQuery = $connection->prepare('SELECT precio, stock FROM productos WHERE id = ? AND estado = 1 LIMIT 1');
        $productQuery->bind_param('i', $productId);
        $productQuery->execute();
        $product = $productQuery->get_result()->fetch_assoc();
        $productQuery->close();
        if (!$product) throw new DomainException('Uno de los productos ya no está disponible.');
        if ((int) $product['stock'] < $quantity) throw new DomainException('No hay stock suficiente para completar la compra.');
        $totalCents += (int) round((float) $product['precio'] * 100) * $quantity;
    }
    $totalCop = $totalCents / 100;
    $amountUsd = paypalAmountUsd($totalCents, (float) $configuration['copPerUsd']);
    if ((float) $amountUsd <= 0) throw new DomainException('El total no es válido para PayPal.');
    $connection->commit();

    // 2. Crear la orden en PayPal fuera de la transacción de base de datos.
    $paypalOrder = paypalApiRequest('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $key,
            'custom_id' => $key,
            'description' => 'Pedido LiquorSoft',
            'amount' => ['currency_code' => 'USD', 'value' => $amountUsd],
        ]],
    ], ['PayPal-Request-Id: ' . $key]);
    $paypalOrderId = (string) ($paypalOrder['id'] ?? '');
    if ($paypalOrderId === '' || ($paypalOrder['status'] ?? '') !== 'CREATED') {
        throw new RuntimeException('PayPal devolvió una orden incompleta.');
    }

    // 3. Persistir la intención. Si otra solicitud ganó la carrera, devolver la existente.
    $insert = $connection->prepare(
        "INSERT INTO paypal_checkout_intents
         (paypal_order_id, usuario_id, clave_idempotencia, amount_cop, amount_usd, items_json, direccion_entrega, notas, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'CREATED')"
    );
    $insert->bind_param('sisddsss', $paypalOrderId, $userId, $key, $totalCop, $amountUsd, $itemsJson, $address, $notes);
    if (!$insert->execute()) {
        if ($insert->errno !== 1062) {
            throw new RuntimeException('No fue posible guardar la orden de PayPal.');
        }
        $insert->close();
        $existingQuery = $connection->prepare(
            'SELECT paypal_order_id, items_json, amount_usd, estado
             FROM paypal_checkout_intents WHERE usuario_id = ? AND clave_idempotencia = ? LIMIT 1'
        );
        $existingQuery->bind_param('is', $userId, $key);
        $existingQuery->execute();
        $existing = $existingQuery->get_result()->fetch_assoc();
        $existingQuery->close();
        if ($existing && $existing['items_json'] === $itemsJson && $existing['estado'] === 'CREATED') {
            $connection->close();
            jsonResponse(200, [
                'orderId' => (string) $existing['paypal_order_id'],
                'currency' => 'USD',
                'amount' => (string) $existing['amount_usd'],
            ]);
        }
        throw new DomainException('Esta clave de compra ya fue usada para otros datos.');
    }
    $insert->close();
    $connection->close();

    jsonResponse(201, ['orderId' => $paypalOrderId, 'currency' => 'USD', 'amount' => $amountUsd]);
} catch (Throwable $error) {
    if ($connection->connect_errno === 0) {
        try {
            $connection->rollback();
        } catch (Throwable) {
            // La transacción pudo haber terminado antes del error.
        }
    }
    $connection->close();
    if ($error instanceof DomainException) jsonResponse(422, ['message' => $error->getMessage()]);
    error_log('LiquorSoft PayPal order creation failed: ' . $error->getMessage());
    jsonResponse(502, ['message' => 'No fue posible iniciar el pago con PayPal. Intenta nuevamente.']);
}
