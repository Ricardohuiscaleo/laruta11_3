<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$config_paths = [
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

    // Obtener ingredientes
    $stmt_ing = $pdo->query("
        SELECT 
            id,
            name,
            category,
            unit,
            current_stock,
            'ingredient' as type
        FROM ingredients 
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    $ingredientes = $stmt_ing->fetchAll(PDO::FETCH_ASSOC);

    // Obtener productos (bebidas, etc)
    $stmt_prod = $pdo->query("
        SELECT 
            p.id,
            p.name,
            c.name as category,
            'unidad' as unit,
            p.stock_quantity as current_stock,
            'product' as type,
            p.category_id,
            p.subcategory_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        ORDER BY p.name ASC
    ");
    $productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

    // Combinar ambos arrays
    $items = array_merge($ingredientes, $productos);

    echo json_encode($items);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
