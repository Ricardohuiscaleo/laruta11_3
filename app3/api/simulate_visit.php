<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Simular una visita
    $stmt = $pdo->prepare("
        INSERT INTO site_visits 
        (ip_address, user_agent, page_url, referrer, session_id, visit_date, device_type, browser) 
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?)
    ");
    
    $stmt->execute([
        '127.0.0.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
        'https://app.laruta11.cl',
        '',
        'test-' . time(),
        'mobile',
        'Safari'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Visit simulated successfully',
        'visit_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>