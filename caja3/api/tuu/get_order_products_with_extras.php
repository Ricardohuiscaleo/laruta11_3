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
    
    // Crear conexión PDO directamente si no existe
    if (!isset($pdo)) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
                "u958525313_app",
                "wEzho0-hujzoz-cevzin",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
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