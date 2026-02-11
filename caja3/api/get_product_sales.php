<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
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

$product_id = $_GET['product_id'] ?? '';
$period = $_GET['period'] ?? 'month';

if (empty($product_id)) {
    echo json_encode(['success' => false, 'error' => 'Product ID required']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Calcular fechas según período
    $today = new DateTime();
    if ($period === 'month') {
        $start_date = $today->format('Y-m-01');
        $end_date = $today->format('Y-m-d');
    } else {
        $start_date = '2024-10-14'; // Inicio operaciones
        $end_date = $today->format('Y-m-d');
    }
    
    // Obtener nombre del producto
    $productQuery = "SELECT name FROM products WHERE id = :product_id";
    $productStmt = $pdo->prepare($productQuery);
    $productStmt->execute([':product_id' => $product_id]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }
    
    // Obtener ventas del producto desde tuu_order_items
    $query = "SELECT 
                SUM(oi.subtotal) as total_revenue,
                SUM(oi.quantity) as total_quantity
              FROM tuu_order_items oi
              JOIN tuu_orders o ON oi.order_id = o.id
              WHERE oi.product_id = :product_id
              AND DATE(o.created_at) BETWEEN :start_date AND :end_date
              AND o.payment_status = 'paid'";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':product_id' => $product_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_revenue' => floatval($data['total_revenue'] ?? 0),
        'total_quantity' => intval($data['total_quantity'] ?? 0),
        'product_name' => $product['name'],
        'product_id' => $product_id,
        'period' => $period
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
