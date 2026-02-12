<?php
$config = require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );

    $stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}