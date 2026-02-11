<?php
// Script para configurar cron job automático
// Ejecutar una vez en producción: https://app.laruta11.cl/api/tuu/setup_cron.php

header('Content-Type: application/json');

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Crear tabla de control de cron
    $createTable = "
    CREATE TABLE IF NOT EXISTS tuu_sync_control (
        id INT PRIMARY KEY DEFAULT 1,
        last_sync_date DATE,
        last_sync_time DATETIME,
        status ENUM('running', 'completed', 'error') DEFAULT 'completed',
        message TEXT,
        transactions_synced INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($createTable);
    
    // Insertar registro inicial
    $insertControl = "INSERT IGNORE INTO tuu_sync_control (id, message) VALUES (1, 'Cron configurado')";
    $pdo->exec($insertControl);
    
    // Crear archivo de cron
    $cronScript = '#!/bin/bash
# Cron job para sincronización TUU
# Ejecutar cada 30 minutos: */30 * * * *

curl -s "https://app.laruta11.cl/api/tuu/daily_sync.php" > /dev/null 2>&1
';
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabla de control creada',
        'cron_command' => '*/30 * * * * curl -s "https://app.laruta11.cl/api/tuu/daily_sync.php" > /dev/null 2>&1',
        'instructions' => [
            '1. Ejecutar fix_sync_gap.php para recuperar datos faltantes',
            '2. Configurar cron job con el comando mostrado',
            '3. Verificar estado con cron_status.php'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>