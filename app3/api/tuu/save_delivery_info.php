<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_number = $input['order_number'] ?? null;
    $delivery_type = $input['delivery_type'] ?? 'pickup';
    $delivery_address = $input['delivery_address'] ?? null;
    $customer_notes = $input['customer_notes'] ?? null;
    $pickup_time = $input['pickup_time'] ?? null;
    
    if (!$order_number) {
        throw new Exception('Número de orden requerido');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Actualizar orden con datos de delivery
    $sql = "UPDATE tuu_orders SET 
            delivery_type = ?, 
            delivery_address = ?, 
            customer_notes = ?,
            special_instructions = ?
            WHERE order_number = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $delivery_type,
        $delivery_address,
        $customer_notes,
        $pickup_time, // Usar special_instructions para pickup_time
        $order_number
    ]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Datos de delivery guardados correctamente'
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