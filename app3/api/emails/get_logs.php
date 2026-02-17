<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Verificar autenticación admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $config_paths = [
        __DIR__ . '/../../config.php',
        __DIR__ . '/../../../config.php',
        __DIR__ . '/../../../../config.php'
    ];
    
    $config = null;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            $config = require_once $path;
            break;
        }
    }
    
    if (!$config) {
        throw new Exception('Config no encontrado');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar si existe la tabla
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => true,
            'logs' => [],
            'message' => 'No hay logs disponibles'
        ]);
        exit;
    }
    
    // Obtener últimos 50 logs
    $stmt = $pdo->prepare("
        SELECT 
            to_email as `to`,
            subject,
            method,
            status,
            error_message,
            created_at
        FROM email_logs 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>