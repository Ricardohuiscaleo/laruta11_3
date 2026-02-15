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

    // Obtener TODOS los productos con sus categorías
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
        ORDER BY p.id DESC
        LIMIT 50
    ");
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_products' => count($products),
        'products' => $products
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
