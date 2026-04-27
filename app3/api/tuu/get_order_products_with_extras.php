<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['order'])) {
    echo json_encode(['success' => false, 'error' => 'Order reference required']);
    exit;
}

try {
    // Buscar config.php en múltiples niveles
    $config_paths = [
        __DIR__ . '/../../config.php',     // 2 niveles
        __DIR__ . '/../../../config.php',  // 3 niveles  
        __DIR__ . '/../../../../config.php' // 4 niveles
    ];
    
    $config_loaded = false;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $config_loaded = true;
            break;
        }
    }
    
    if (!$config_loaded) {
        throw new Exception('Config file not found');
    }
    
    // Usar config para conexión BD (config.php retorna array)
    $config = null;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            $config = require $path;
            break;
        }
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $orderRef = $_GET['order'];
    
    // Obtener productos del pedido con tipo de item
    $stmt = $pdo->prepare("
        SELECT 
            toi.product_id,
            toi.product_name,
            toi.product_price as price,
            toi.quantity,
            toi.subtotal,
            toi.item_type,
            toi.combo_data,
            tuo.customer_notes,
            tuo.special_instructions,
            tuo.delivery_type,
            tuo.delivery_address
        FROM tuu_order_items toi
        JOIN tuu_orders tuo ON toi.order_reference = tuo.order_number
        WHERE toi.order_reference = ?
        ORDER BY 
            CASE toi.item_type 
                WHEN 'product' THEN 1
                WHEN 'personalizar' THEN 2
                WHEN 'acompañamiento' THEN 3
                WHEN 'extras' THEN 4
                ELSE 5
            END,
            toi.id
    ");
    
    $stmt->execute([$orderRef]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo json_encode(['success' => false, 'error' => 'No products found']);
        exit;
    }
    
    // Obtener notas del cliente de la primera fila
    $customerNotes = $products[0]['customer_notes'] ?? '';
    $specialInstructions = $products[0]['special_instructions'] ?? '';
    $deliveryType = $products[0]['delivery_type'] ?? 'pickup';
    $deliveryAddress = $products[0]['delivery_address'] ?? '';
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'customer_notes' => $customerNotes,
        'special_instructions' => $specialInstructions,
        'delivery_type' => $deliveryType,
        'delivery_address' => $deliveryAddress
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>