<?php
header('Content-Type: application/json');

$config_paths = [
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
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $order_reference = $_GET['order'] ?? 'R11-1757087194-5677';
    
    // Obtener pedido principal
    $order_sql = "SELECT * FROM tuu_orders WHERE order_number = ? OR order_reference = ?";
    $stmt = $pdo->prepare($order_sql);
    $stmt->execute([$order_reference, $order_reference]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener items del pedido
    $items_sql = "SELECT * FROM tuu_order_items WHERE order_reference = ?";
    $stmt = $pdo->prepare($items_sql);
    $stmt->execute([$order_reference]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'order_reference' => $order_reference,
        'order' => $order,
        'items' => $items,
        'has_items' => count($items) > 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>