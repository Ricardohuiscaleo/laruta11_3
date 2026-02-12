<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
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
    $order_id = $_GET['order_id'] ?? null;
    
    if (!$order_id) {
        echo json_encode(['success' => false, 'error' => 'Order ID requerido']);
        exit;
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener orden principal
    $order_sql = "SELECT * FROM tuu_orders WHERE order_number = ?";
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
        exit;
    }
    
    // Obtener items del pedido
    $items_sql = "SELECT * FROM tuu_order_items WHERE order_reference = ?";
    $items_stmt = $pdo->prepare($items_sql);
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay items en tuu_order_items, usar datos de la orden principal
    if (empty($items)) {
        // Parsear product_name que contiene el resumen
        $product_summary = $order['product_name'];
        $formatted_items = [[
            'id' => 1,
            'name' => $product_summary,
            'price' => (int)$order['product_price'],
            'quantity' => 1
        ]];
    } else {
    
        // Formatear items para el frontend
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_item = [
                'id' => $item['product_id'],
                'name' => $item['product_name'],
                'price' => (int)$item['product_price'],
                'quantity' => (int)$item['quantity']
            ];
            
            // Si es combo o tiene personalizaciones, agregar datos adicionales
            if ($item['combo_data']) {
                $combo_data = json_decode($item['combo_data'], true);
                error_log("combo_data for item {$item['product_name']}: " . json_encode($combo_data));
                
                if ($item['item_type'] === 'combo') {
                    $formatted_item['type'] = 'combo';
                    $formatted_item['fixed_items'] = $combo_data['fixed_items'] ?? [];
                    $formatted_item['selections'] = $combo_data['selections'] ?? [];
                }
                
                // Agregar personalizaciones si existen
                if (isset($combo_data['customizations'])) {
                    $formatted_item['customizations'] = $combo_data['customizations'];
                    error_log("Found customizations: " . json_encode($combo_data['customizations']));
                }
            } else {
                error_log("No combo_data for item {$item['product_name']}");
            }
            
            $formatted_items[] = $formatted_item;
        }
    }
    
    echo json_encode([
        'success' => true,
        'order' => [
            'id' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'delivery_type' => $order['delivery_type'],
            'delivery_address' => $order['delivery_address'],
            'customer_notes' => $order['customer_notes'],
            'total' => (int)$order['installment_amount'],
            'delivery_fee' => (int)$order['delivery_fee'],
            'items' => $formatted_items
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get Transfer Order Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>