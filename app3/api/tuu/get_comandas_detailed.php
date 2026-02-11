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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener pedidos activos con detalles de productos
    $sql = "SELECT o.*, 
                   GROUP_CONCAT(
                       CONCAT(oi.product_name, ' x', oi.quantity) 
                       SEPARATOR ', '
                   ) as detailed_products,
                   GROUP_CONCAT(oi.product_name SEPARATOR '|') as product_names,
                   COUNT(oi.id) as items_count
            FROM tuu_orders o
            LEFT JOIN tuu_order_items oi ON o.id = oi.order_id
            WHERE o.order_status != 'delivered' 
            AND o.payment_status = 'paid'
            GROUP BY o.id
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar cada pedido para añadir información de productos
    foreach ($orders as &$order) {
        if ($order['detailed_products'] && $order['items_count'] > 0) {
            // Tiene productos detallados en tuu_order_items
            $order['has_detailed_items'] = true;
            $order['main_product'] = explode('|', $order['product_names'])[0]; // Primer producto para imagen
        } else {
            // Solo tiene product_name en tuu_orders
            $order['has_detailed_items'] = false;
            $order['detailed_products'] = null;
            $order['main_product'] = $order['product_name'];
        }
        
        // Limpiar datos para evitar duplicación
        if (!$order['has_detailed_items']) {
            unset($order['detailed_products']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>