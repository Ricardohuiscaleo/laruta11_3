<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://app.laruta11.cl');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

// Usar conexión app_db (donde está tuu_order_items)
try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error conexión BD']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$order = $_GET['order'] ?? '';

if (empty($order)) {
    echo json_encode(['success' => false, 'error' => 'Número de orden requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT product_name, quantity, product_price as price 
        FROM tuu_order_items 
        WHERE order_reference = ?
        ORDER BY id ASC
    ");
    
    $stmt->execute([$order]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error obteniendo productos: ' . $e->getMessage()
    ]);
}
?>