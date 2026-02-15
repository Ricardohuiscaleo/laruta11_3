<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
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

    // Productos con IDs tipo timestamp (> 1000000000)
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.category_id,
            p.subcategory_id,
            p.is_active,
            p.image_url,
            c.name as category_name,
            s.name as subcategory_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        WHERE p.id > 1000000000
        ORDER BY p.id DESC
    ");
    
    $timestampProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Productos con IDs normales (< 1000)
    $stmt2 = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.category_id,
            p.subcategory_id,
            p.is_active,
            c.name as category_name,
            s.name as subcategory_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        WHERE p.id < 1000
        ORDER BY p.id ASC
    ");
    
    $normalProducts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'timestamp_products' => [
            'count' => count($timestampProducts),
            'products' => $timestampProducts
        ],
        'normal_products' => [
            'count' => count($normalProducts),
            'products' => $normalProducts
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
