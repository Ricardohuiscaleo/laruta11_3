<?php
$config = require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host={$config['Calcularuta11_db_host']};dbname={$config['Calcularuta11_db_name']}",
        $config['Calcularuta11_db_user'],
        $config['Calcularuta11_db_pass']
    );

    // Obtener estadísticas por POS
    $stmt = $pdo->prepare("
        SELECT 
            pos_device,
            cart_type,
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'Completed' THEN amount ELSE 0 END) as total_amount,
            MAX(created_at) as last_transaction
        FROM tuu_payments 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY pos_device, cart_type
        ORDER BY pos_device, cart_type
    ");
    
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Información de dispositivos configurados
    $devices = [];
    foreach ($config['tuu_devices'] as $key => $device) {
        $devices[$key] = [
            'name' => $device['name'],
            'location' => $device['location'],
            'serial' => $device['serial'],
            'configured' => $device['serial'] !== 'PENDING_DEVICE_2'
        ];
    }

    echo json_encode([
        'success' => true,
        'devices' => $devices,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}