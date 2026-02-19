<?php
// Script para simular callback de pago RL6 exitoso
// Uso: php simulate_callback.php

$config = require_once __DIR__ . '/../../config.php';

$user_id = 4; // Usuario de prueba

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Obtener datos del usuario
    $user_sql = "SELECT credito_usado, email, nombre, grado_militar FROM usuarios WHERE id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("Usuario $user_id no encontrado\n");
    }
    
    $amount = $user['credito_usado'];
    
    if ($amount <= 0) {
        die("Usuario no tiene deuda pendiente\n");
    }
    
    echo "Usuario: {$user['nombre']} ({$user['grado_militar']})\n";
    echo "Email: {$user['email']}\n";
    echo "Deuda: $" . number_format($amount, 0, ',', '.') . "\n\n";
    
    // Crear orden de pago simulada
    $order_id = 'RL6-' . time() . '-' . rand(1000, 9999);
    
    $insert_sql = "INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone,
        product_name, product_price, installment_amount,
        status, payment_status, order_status, pagado_con_credito_rl6
    ) VALUES (?, ?, ?, '', 'Pago de crédito RL6', ?, ?, 'pending', 'unpaid', 'pending', 0)";
    
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([
        $order_id,
        $user_id,
        $user['nombre'],
        $amount,
        $amount
    ]);
    
    echo "Orden creada: $order_id\n\n";
    
    // Simular callback exitoso
    $callback_url = 'http://localhost:4322/api/rl6/payment_callback.php';
    $params = http_build_query([
        'x_reference' => $order_id,
        'x_result' => 'completed',
        'x_transaction_id' => 'SIM-' . time(),
        'x_amount' => $amount
    ]);
    
    echo "Llamando callback: $callback_url?$params\n\n";
    
    $ch = curl_init("$callback_url?$params");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n";
    echo "Response:\n$response\n\n";
    
    // Verificar resultado
    $check_sql = "SELECT credito_usado, fecha_ultimo_pago, credito_bloqueado FROM usuarios WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$user_id]);
    $updated_user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Estado final:\n";
    echo "- Crédito usado: $" . number_format($updated_user['credito_usado'], 0, ',', '.') . "\n";
    echo "- Último pago: {$updated_user['fecha_ultimo_pago']}\n";
    echo "- Bloqueado: " . ($updated_user['credito_bloqueado'] ? 'SÍ' : 'NO') . "\n\n";
    
    // Verificar transacción
    $trans_sql = "SELECT * FROM rl6_credit_transactions WHERE order_id = ?";
    $trans_stmt = $pdo->prepare($trans_sql);
    $trans_stmt->execute([$order_id]);
    $transaction = $trans_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction) {
        echo "Transacción registrada:\n";
        echo "- ID: {$transaction['id']}\n";
        echo "- Tipo: {$transaction['type']}\n";
        echo "- Monto: $" . number_format($transaction['amount'], 0, ',', '.') . "\n";
        echo "- Descripción: {$transaction['description']}\n";
    } else {
        echo "⚠️ No se encontró transacción registrada\n";
    }
    
    // Verificar email log
    $email_sql = "SELECT * FROM email_logs WHERE order_id = ?";
    $email_stmt = $pdo->prepare($email_sql);
    $email_stmt->execute([$order_id]);
    $email_log = $email_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($email_log) {
        echo "\nEmail enviado:\n";
        echo "- Para: {$email_log['email_to']}\n";
        echo "- Tipo: {$email_log['email_type']}\n";
        echo "- Estado: {$email_log['status']}\n";
        echo "- Gmail ID: {$email_log['gmail_message_id']}\n";
    } else {
        echo "\n⚠️ No se encontró log de email\n";
    }
    
    echo "\n✅ Simulación completada\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
