<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
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
    
    // Papas específicas (ID 17)
    $papas = $pdo->query("SELECT id, name, price, image_url FROM productos WHERE id = 17")->fetch(PDO::FETCH_ASSOC);
    
    // Bebidas elegibles (categoría 5, subcategoría 11, cost_price != 750)
    $bebidas = $pdo->query("
        SELECT id, name, price, image_url 
        FROM productos 
        WHERE category_id = 5 
        AND subcategory_id = 11 
        AND cost_price != 750
        AND is_active = 1
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'papas' => $papas,
        'bebidas' => $bebidas
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
