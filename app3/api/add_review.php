<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log('Review input: ' . print_r($input, true));

$product_id = $input['product_id'] ?? null;
$customer_name = trim($input['customer_name'] ?? '');
$rating = (int)($input['rating'] ?? 0);
$comment = trim($input['comment'] ?? '');

// Validaciones
if (!$product_id || !$customer_name || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

if (strlen($customer_name) < 2 || strlen($customer_name) > 100) {
    echo json_encode(['success' => false, 'error' => 'El nombre debe tener entre 2 y 100 caracteres']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar que el producto existe
    $product_check = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $product_check->execute([$product_id]);
    if (!$product_check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }

    // Obtener IP del cliente
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Verificar si usuario está logueado
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Insertar reseña
    $stmt = $pdo->prepare("
        INSERT INTO reviews (product_id, user_id, customer_name, rating, comment, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$product_id, $user_id, $customer_name, $rating, $comment, $ip_address]);
    
    $review_id = $pdo->lastInsertId();
    error_log('Review created with ID: ' . $review_id);
    
    echo json_encode([
        'success' => true,
        'message' => '¡Gracias por tu reseña!',
        'review_id' => $review_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>