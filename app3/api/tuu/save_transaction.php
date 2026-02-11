<?php
header('Content-Type: application/json');

try {
    // Buscar config.php en múltiples niveles
    $config_paths = [
        __DIR__ . '/../../config.php',     // 2 niveles
        __DIR__ . '/../../../config.php',  // 3 niveles  
        __DIR__ . '/../../../../config.php' // 4 niveles
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
    
    $sql = "INSERT INTO tuu_pagos_online (
        user_id, 
        order_reference, 
        amount, 
        payment_method, 
        tuu_transaction_id, 
        status, 
        customer_name,
        customer_email,
        customer_phone,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isdssssss", 
        $input['user_id'],
        $input['order_reference'],
        $input['amount'],
        $input['payment_method'],
        $input['tuu_transaction_id'],
        $input['status'],
        $input['customer_name'],
        $input['customer_email'],
        $input['customer_phone']
    );
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Error guardando transacción');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>