<?php
// Probar envío de email directamente
// Acceder: https://app.laruta11.cl/api/rl6/test_email.php

header('Content-Type: text/plain; charset=utf-8');

$user_id = 4;
$order_id = 'RL6-TEST-' . time();
$amount = 15000;

echo "🔄 Probando envío de email...\n\n";
echo "Usuario: $user_id\n";
echo "Orden: $order_id\n";
echo "Monto: $" . number_format($amount, 0, ',', '.') . "\n\n";

$ch = curl_init('https://app.laruta11.cl/api/gmail/send_payment_confirmation.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'user_id' => $user_id,
    'order_id' => $order_id,
    'amount' => $amount
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($curl_error) {
    echo "cURL Error: $curl_error\n";
}
echo "Response: $response\n\n";

// Verificar log
$config = require_once __DIR__ . '/../../config.php';
$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

$sql = "SELECT * FROM email_logs WHERE order_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$log = mysqli_fetch_assoc($result);

if ($log) {
    echo "✅ Email log encontrado:\n";
    echo "- Estado: {$log['status']}\n";
    echo "- Gmail ID: {$log['gmail_message_id']}\n";
    if ($log['error_message']) {
        echo "- Error: {$log['error_message']}\n";
    }
} else {
    echo "⚠️ No se encontró log de email\n";
}

mysqli_close($conn);
