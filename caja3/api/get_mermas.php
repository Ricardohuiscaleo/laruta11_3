<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
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
    echo json_encode([]);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query("
        SELECT m.*, 
               COALESCE(m.item_name, i.name, p.name) as item_name,
               m.item_type
        FROM mermas m
        LEFT JOIN ingredients i ON m.ingredient_id = i.id AND m.item_type = 'ingredient'
        LEFT JOIN products p ON m.product_id = p.id AND m.item_type = 'product'
        ORDER BY m.created_at DESC
        LIMIT 100
    ");
    $mermas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($mermas);
} catch (Exception $e) {
    echo json_encode([]);
}
?>
