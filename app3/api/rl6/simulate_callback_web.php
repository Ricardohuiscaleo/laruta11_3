<?php
// Script web para simular callback RL6
// Acceder: https://app.laruta11.cl/api/rl6/simulate_callback_web.php

header('Content-Type: text/plain; charset=utf-8');

$config = require_once __DIR__ . '/../../config.php';

$user_id = 4;

try {
    $conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );
    
    if (!$conn) {
        die("❌ Error de conexión: " . mysqli_connect_error());
    }
    
    echo "✅ Conectado a base de datos\n\n";

    // Obtener usuario
    $user_sql = "SELECT credito_usado, email, nombre, grado_militar FROM usuarios WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $result = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($result);
    
    if (!$user) {
        die("❌ Usuario $user_id no encontrado\n");
    }
    
    $amount = $user['credito_usado'];
    
    if ($amount <= 0) {
        die("❌ Usuario no tiene deuda pendiente\n");
    }
    
    echo "Usuario: {$user['nombre']} ({$user['grado_militar']})\n";
    echo "Email: {$user['email']}\n";
    echo "Deuda: $" . number_format($amount, 0, ',', '.') . "\n\n";
    
    // Crear orden
    $order_id = 'RL6-' . time() . '-' . rand(1000, 9999);
    
    $insert_sql = "INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone,
        product_name, product_price, installment_amount,
        status, payment_status, order_status, pagado_con_credito_rl6
    ) VALUES (?, ?, ?, '', 'Pago de crédito RL6', ?, ?, 'pending', 'unpaid', 'pending', 0)";
    
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "sisdd", $order_id, $user_id, $user['nombre'], $amount, $amount);
    mysqli_stmt_execute($insert_stmt);
    
    echo "✅ Orden creada: $order_id\n\n";
    
    // Simular callback
    $callback_url = 'https://app.laruta11.cl/api/rl6/payment_callback.php';
    $params = http_build_query([
        'x_reference' => $order_id,
        'x_result' => 'completed',
        'x_transaction_id' => 'SIM-' . time(),
        'x_amount' => $amount
    ]);
    
    echo "🔄 Llamando callback...\n";
    
    $ch = curl_init("$callback_url?$params");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n\n";
    
    // Verificar resultado
    $check_sql = "SELECT credito_usado, fecha_ultimo_pago, credito_bloqueado FROM usuarios WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $updated_user = mysqli_fetch_assoc($check_result);
    
    echo "📊 Estado final:\n";
    echo "- Crédito usado: $" . number_format($updated_user['credito_usado'], 0, ',', '.') . "\n";
    echo "- Último pago: {$updated_user['fecha_ultimo_pago']}\n";
    echo "- Bloqueado: " . ($updated_user['credito_bloqueado'] ? 'SÍ' : 'NO') . "\n\n";
    
    // Verificar transacción
    $trans_sql = "SELECT * FROM rl6_credit_transactions WHERE order_id = ?";
    $trans_stmt = mysqli_prepare($conn, $trans_sql);
    mysqli_stmt_bind_param($trans_stmt, "s", $order_id);
    mysqli_stmt_execute($trans_stmt);
    $trans_result = mysqli_stmt_get_result($trans_stmt);
    $transaction = mysqli_fetch_assoc($trans_result);
    
    if ($transaction) {
        echo "✅ Transacción registrada:\n";
        echo "- Tipo: {$transaction['type']}\n";
        echo "- Monto: $" . number_format($transaction['amount'], 0, ',', '.') . "\n";
    } else {
        echo "⚠️ No se encontró transacción\n";
    }
    
    // Verificar email
    $email_sql = "SELECT * FROM email_logs WHERE order_id = ?";
    $email_stmt = mysqli_prepare($conn, $email_sql);
    mysqli_stmt_bind_param($email_stmt, "s", $order_id);
    mysqli_stmt_execute($email_stmt);
    $email_result = mysqli_stmt_get_result($email_stmt);
    $email_log = mysqli_fetch_assoc($email_result);
    
    if ($email_log) {
        echo "\n✅ Email enviado:\n";
        echo "- Para: {$email_log['email_to']}\n";
        echo "- Estado: {$email_log['status']}\n";
        echo "- Gmail ID: {$email_log['gmail_message_id']}\n";
    } else {
        echo "\n⚠️ No se encontró log de email\n";
    }
    
    echo "\n✅ Simulación completada\n";
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
