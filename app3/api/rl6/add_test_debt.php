<?php
// Script para agregar deuda de prueba al usuario 4
// Acceder: https://app.laruta11.cl/api/rl6/add_test_debt.php

header('Content-Type: text/plain; charset=utf-8');

$config = require_once __DIR__ . '/../../config.php';

$user_id = 4;
$test_amount = 15000; // $15.000 de prueba

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
    
    // Obtener usuario
    $user_sql = "SELECT nombre, grado_militar, credito_usado, limite_credito FROM usuarios WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $result = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($result);
    
    if (!$user) {
        die("❌ Usuario $user_id no encontrado\n");
    }
    
    echo "Usuario: {$user['nombre']} ({$user['grado_militar']})\n";
    echo "Crédito actual: $" . number_format($user['credito_usado'], 0, ',', '.') . "\n";
    echo "Límite: $" . number_format($user['limite_credito'], 0, ',', '.') . "\n\n";
    
    // Agregar deuda de prueba
    $update_sql = "UPDATE usuarios SET credito_usado = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "di", $test_amount, $user_id);
    mysqli_stmt_execute($update_stmt);
    
    echo "✅ Deuda de prueba agregada: $" . number_format($test_amount, 0, ',', '.') . "\n\n";
    echo "Ahora puedes ejecutar: https://app.laruta11.cl/api/rl6/simulate_callback_web.php\n";
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
