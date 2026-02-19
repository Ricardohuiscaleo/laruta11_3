<?php
// Ver logs de email
// Acceder: https://app.laruta11.cl/api/rl6/check_email_logs.php

header('Content-Type: text/plain; charset=utf-8');

$config = require_once __DIR__ . '/../../config.php';

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
    
    // Obtener últimos 5 logs de email
    $sql = "SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 5";
    $result = mysqli_query($conn, $sql);
    
    echo "📧 Últimos 5 emails enviados:\n\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$row['id']}\n";
        echo "Usuario: {$row['user_id']}\n";
        echo "Para: {$row['email_to']}\n";
        echo "Tipo: {$row['email_type']}\n";
        echo "Orden: {$row['order_id']}\n";
        echo "Monto: $" . number_format($row['amount'], 0, ',', '.') . "\n";
        echo "Estado: {$row['status']}\n";
        if ($row['gmail_message_id']) {
            echo "Gmail ID: {$row['gmail_message_id']}\n";
        }
        if ($row['error_message']) {
            echo "Error: {$row['error_message']}\n";
        }
        echo "Fecha: {$row['sent_at']}\n";
        echo "\n";
    }
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
