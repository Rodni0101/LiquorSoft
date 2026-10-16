<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    jsonResponse(405, ['message' => 'Método no permitido.']);
}

$payload = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($payload['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 1000) {
    jsonResponse(422, ['message' => 'Escribe una pregunta de hasta 1000 caracteres.']);
}

liquorsoftSessionStart();
if (!isset($_SESSION['chat_last_request'])) {
    $_SESSION['chat_last_request'] = 0;
}
if (time() - (int) $_SESSION['chat_last_request'] < 2) {
    jsonResponse(429, ['message' => 'Espera un momento antes de enviar otra pregunta.']);
}
$_SESSION['chat_last_request'] = time();

$connection = databaseConnection();
$normalized = chatNormalize($message);
$context = is_array($_SESSION['chat_context'] ?? null) ? $_SESSION['chat_context'] : [];
$budget = chatExtractBudget($message);
$category = chatExtractCategory($normalized);
$followUpCheaper = preg_match('/\b(mas barato|mas economico|mas economica|uno mas barato|otra opcion)\b/', $normalized) === 1;

if ($followUpCheaper && isset($context['category'])) {
    $category = (string) $context['category'];
    $budget = isset($context['last_price']) ? (float) $context['last_price'] : $budget;
}

if (preg_match('/\b(envios?|domicilio|entregas?|horarios?|devoluciones?|direcciones?)\b/', $normalized)) {
    $answer = 'La tienda aún no tiene políticas de envío u horarios configurados en el sistema. Contacta al equipo de LiquorSoft para confirmarlos.';
    $products = [];
} elseif (preg_match('/\b(cuantos|cuantas|numero|total)\b/', $normalized) === 1
    && preg_match('/\b(productos|referencias|articulos|bebidas)\b/', $normalized) === 1) {
    $result = $connection->query(
        'SELECT COUNT(*) AS active_products,
                COALESCE(SUM(stock > 0), 0) AS available_products
         FROM productos WHERE estado = 1'
    );
    if (!$result) {
        $errorCode = $connection->errno;
        $connection->close();
        error_log('LiquorSoft chat product count query failed (code ' . $errorCode . ').');
        jsonResponse(500, ['message' => 'No fue posible consultar el catálogo.']);
    }
    $counts = $result->fetch_assoc() ?: ['active_products' => 0, 'available_products' => 0];
    $answer = 'Hay ' . (int) $counts['active_products'] . ' productos activos en el catálogo; '
        . (int) $counts['available_products'] . ' tienen existencias disponibles.';
    $products = [];
} elseif (preg_match('/\b(pedidos?|orden(?:es)?)\b/', $normalized)) {
    $user = currentUser($connection);
    if (!$user) {
        $answer = 'Para consultar el estado de tus pedidos, inicia sesión y revisa la sección “Mis pedidos”.';
        $products = [];
    } else {
        $statement = $connection->prepare(
            'SELECT numero, estado FROM pedidos WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5'
        );
        $userId = (int) $user['id'];
        $statement->bind_param('i', $userId);
        $statement->execute();
        $result = $statement->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row['numero'] . ': ' . $row['estado'];
        }
        $statement->close();
        $answer = $orders ? 'Estos son tus pedidos recientes: ' . implode('; ', $orders) . '.' : 'No encontré pedidos asociados a tu cuenta.';
        $products = [];
    }
} else {
    $sql = "SELECT p.id, p.nombre AS name, COALESCE(c.nombre, 'Sin categoría') AS category,
                   COALESCE(p.descripcion, '') AS description,
                   p.precio AS price, p.stock
            FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE p.estado = 1 AND p.stock > 0";
    $types = '';
    $values = [];
    if ($category !== null) {
        $sql .= ' AND LOWER(c.nombre) = ?';
        $types .= 's';
        $values[] = $category;
    }
    if ($budget !== null) {
        $sql .= ' AND p.precio <= ?';
        $types .= 'd';
        $values[] = $budget;
    }
    if ($followUpCheaper && isset($context['last_price'])) {
        $sql .= ' AND p.precio < ?';
        $types .= 'd';
        $values[] = (float) $context['last_price'];
    }
    $sql .= ' ORDER BY p.precio ASC, p.nombre ASC LIMIT 6';

    $statement = $connection->prepare($sql);
    if (!$statement) {
        $errorCode = $connection->errno;
        $connection->close();
        error_log('LiquorSoft chat product search could not be prepared (code ' . $errorCode . ').');
        jsonResponse(500, ['message' => 'No fue posible consultar el catálogo.']);
    }
    if ($types !== '') {
        $statement->bind_param($types, ...$values);
    }
    if (!$statement->execute()) {
        $statement->close();
        $connection->close();
        jsonResponse(500, ['message' => 'No fue posible consultar el catálogo.']);
    }
    $result = $statement->get_result();
    $products = [];
    while ($product = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int) $product['id'],
            'name' => (string) $product['name'],
            'category' => (string) $product['category'],
            'description' => (string) $product['description'],
            'price' => (float) $product['price'],
            'stock' => (int) $product['stock'],
            'icon' => productIcon((string) $product['category']),
        ];
    }
    $statement->close();

    if ($products) {
        $answer = $category !== null || $budget !== null
            ? 'Encontré estas opciones disponibles en el catálogo actual.'
            : 'Estas son algunas opciones disponibles. Puedes preguntarme por una categoría o presupuesto.';
        $_SESSION['chat_context'] = [
            'category' => $category,
            'last_price' => (float) $products[count($products) - 1]['price'],
        ];
    } else {
        $answer = 'No encontré productos disponibles con esos criterios. Prueba otra categoría o un presupuesto diferente.';
    }
}

$connection->close();
jsonResponse(200, ['answer' => $answer, 'products' => $products]);

function chatNormalize(string $value): string
{
    return mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
}

function chatExtractBudget(string $message): ?float
{
    if (preg_match('/(?:menos de|hasta|maximo|presupuesto(?: de)?|por debajo de)\s*\$?\s*([\d.,]+)\s*(mil|k)?/i', $message, $matches) !== 1) {
        return null;
    }
    $amount = (float) str_replace([',', '.'], '', $matches[1]);
    if (isset($matches[2]) && $matches[2] !== '') {
        $amount *= 1000;
    }
    return $amount > 0 ? $amount : null;
}

function chatExtractCategory(string $normalized): ?string
{
    foreach (['whisky', 'ron', 'vodka', 'tequila', 'vinos', 'cervezas', 'gin', 'mixers'] as $category) {
        if (preg_match('/\b' . preg_quote($category, '/') . '\b/', $normalized) === 1) {
            return $category;
        }
    }
    return null;
}
