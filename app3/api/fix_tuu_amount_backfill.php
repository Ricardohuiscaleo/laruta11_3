<?php
/**
 * Script para rellenar tuu_amount en órdenes existentes
 * Calcula tuu_amount basado en product_price (que contiene el total)
 */

header('Content-Type: application/json');

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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Contar órdenes con tuu_amount NULL
    $count_sql = "SELECT COUNT(*) as total FROM tuu_orders WHERE tuu_amount IS NULL AND payment_status = 'paid'";
    $count_result = $pdo->query($count_sql)->fetch(PDO::FETCH_ASSOC);
    $total_to_fix = $count_result['total'];
    
    if ($total_to_fix === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'No hay órdenes con tuu_amount NULL',
            'fixed' => 0
        ]);
        exit;
    }
    
    // Actualizar tuu_amount usando product_price (que contiene el total)
    $update_sql = "UPDATE tuu_orders SET tuu_amount = product_price WHERE tuu_amount IS NULL AND payment_status = 'paid'";
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute();
    $fixed = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => 'Backfill completado',
        'total_found' => $total_to_fix,
        'fixed' => $fixed
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
