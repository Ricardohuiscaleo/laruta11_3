<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php hasta 5 niveles
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $order_number = $_GET['order'] ?? null;
    
    if (!$order_number) {
        throw new Exception('Número de orden requerido');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = "SELECT delivery_type, delivery_address, delivery_fee, special_instructions, customer_notes 
            FROM tuu_orders 
            WHERE order_number = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_number]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($delivery) {
        echo json_encode([
            'success' => true,
            'delivery' => $delivery
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Orden no encontrada'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>