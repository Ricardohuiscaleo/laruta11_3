<?php
if (file_exists(__DIR__ . '/../config.php')) {
    $config = require_once __DIR__ . '/../config.php';
} else {
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No se encontró el archivo de configuración']);
        exit;
    }
}

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar pedido #8
    $stmt = $pdo->prepare("SELECT * FROM tuu_orders WHERE id = 8");
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Actualizar manualmente para test
    if ($_GET['update'] ?? false) {
        $updateSql = "UPDATE tuu_orders SET 
            tuu_payment_request_id = 208746,
            tuu_idempotency_key = 'RUTA1168b27429ed50b1756525609',
            tuu_device_used = '6010B232541610747',
            status = 'sent_to_pos'
            WHERE id = 8";
        $pdo->exec($updateSql);
        
        // Volver a consultar
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'order_8' => $order,
        'has_tuu_data' => !empty($order['tuu_payment_request_id']),
        'status' => $order['status'] ?? 'unknown'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>