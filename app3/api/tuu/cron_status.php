<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
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
    
    // Estado del cron
    $control_sql = "SELECT * FROM tuu_sync_control WHERE id = 1";
    $stmt = $pdo->prepare($control_sql);
    $stmt->execute();
    $control = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Estadísticas de transacciones
    $stats_sql = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(amount) as total_amount,
            MAX(payment_date_time) as last_transaction,
            MIN(payment_date_time) as first_transaction,
            COUNT(DISTINCT DATE(payment_date_time)) as days_with_data
        FROM tuu_pos_transactions
    ";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Transacciones por día (últimos 7 días)
    $daily_sql = "
        SELECT 
            DATE(payment_date_time) as date,
            COUNT(*) as transactions,
            SUM(amount) as amount
        FROM tuu_pos_transactions 
        WHERE payment_date_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(payment_date_time)
        ORDER BY date DESC
    ";
    $stmt = $pdo->prepare($daily_sql);
    $stmt->execute();
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'cron_status' => $control ?: [
            'status' => 'not_configured',
            'message' => 'Cron job no configurado aún'
        ],
        'sync_stats' => $stats,
        'daily_stats' => $daily_stats,
        'system_info' => [
            'current_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'next_sync_estimate' => 'Cada 5 minutos'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>