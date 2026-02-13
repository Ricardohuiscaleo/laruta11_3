<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

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

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Config not found']));
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener categorías con conteo de productos
    $sql = "SELECT 
                c.id,
                c.name,
                c.is_active,
                COUNT(p.id) as product_count
            FROM product_categories c
            LEFT JOIN productos p ON p.category_id = c.id
            GROUP BY c.id, c.name, c.is_active
            ORDER BY c.name";
    
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada categoría, obtener productos con miniaturas
    foreach ($categories as &$cat) {
        $sql = "SELECT id, name, image, price 
                FROM productos 
                WHERE category_id = :cat_id 
                AND is_active = 1
                ORDER BY name 
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['cat_id' => $cat['id']]);
        $cat['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cat['is_active'] = (bool)$cat['is_active'];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
