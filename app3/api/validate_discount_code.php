<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($data['code'] ?? ''));
$cart = $data['cart'] ?? [];

if (empty($code)) {
    echo json_encode(['success' => false, 'error' => 'Código vacío']);
    exit;
}

// Obtener códigos de descuento desde config
$discountCodes = $config['discount_codes'] ?? [];

if (empty($discountCodes)) {
    echo json_encode(['success' => false, 'error' => 'Códigos de descuento no configurados']);
    exit;
}

if (!isset($discountCodes[$code])) {
    echo json_encode([
        'success' => false,
        'error' => 'Código inválido',
        'valid' => false
    ]);
    exit;
}

$discount = $discountCodes[$code];
$discountAmount = 0;

// Calcular descuento según tipo
if (isset($discount['product_id'])) {
    // Descuento por producto específico
    foreach ($cart as $item) {
        if ($item['id'] == $discount['product_id']) {
            $discountAmount = round($item['price'] * $item['quantity'] * ($discount['discount_percent'] / 100));
            break;
        }
    }
    
    if ($discountAmount === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Producto no encontrado en el carrito',
            'valid' => false
        ]);
        exit;
    }
} elseif (isset($discount['type']) && $discount['type'] === 'cart') {
    // Descuento sobre todo el carrito
    $cartTotal = 0;
    foreach ($cart as $item) {
        $cartTotal += $item['price'] * $item['quantity'];
    }
    $discountAmount = round($cartTotal * ($discount['discount_percent'] / 100));
}

echo json_encode([
    'success' => true,
    'valid' => true,
    'code' => $code,
    'discount_amount' => $discountAmount,
    'discount_type' => $discount['type'] ?? 'product',
    'discount_percent' => $discount['discount_percent'],
    'product_id' => $discount['product_id'] ?? null,
    'name' => $discount['name']
]);
