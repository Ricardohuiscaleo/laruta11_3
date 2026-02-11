<?php
header('Content-Type: application/json');

try {
    // Buscar config.php en múltiples niveles
    $config_paths = [
        __DIR__ . '/../../config.php',     // 2 niveles
        __DIR__ . '/../../../config.php',  // 3 niveles  
        __DIR__ . '/../../../../config.php', // 4 niveles
        __DIR__ . '/../../../../../config.php' // 5 niveles
    ];
    
    $config = null;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            $config = require_once $path;
            break;
        }
    }
    
    if (!$config) {
        throw new Exception('config.php not found');
    }
    
    $conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );

    if (!$conn) {
        throw new Exception('Error de conexión a BD');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $sql = "UPDATE tuu_pagos_online SET 
        status = ?,
        completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END,
        webpay_response = ?,
        tuu_callback_data = ?,
        updated_at = NOW()
        WHERE tuu_transaction_id = ? OR order_reference = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssss", 
        $input['status'],
        $input['status'],
        json_encode($input['webpay_response'] ?? []),
        json_encode($input['tuu_callback_data'] ?? []),
        $input['tuu_transaction_id'],
        $input['order_reference']
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        echo json_encode([
            'success' => true, 
            'updated' => $affected_rows > 0,
            'affected_rows' => $affected_rows
        ]);
    } else {
        throw new Exception('Error actualizando estado del pago');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>