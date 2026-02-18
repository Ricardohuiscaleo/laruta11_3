<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php en múltiples niveles
$config_paths = [
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
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar si es cajero (mostrar todos) o cliente (solo activos)
    $isCashier = isset($_GET['cashier']) && $_GET['cashier'] === '1';
    $whereClause = $isCashier ? '' : 'WHERE p.is_active = 1 AND c.is_active = 1';
    
    // Obtener productos con estadísticas de reseñas y subcategorías
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.description,
            p.price,
            p.cost_price,
            p.category_id,
            p.subcategory_id,
            p.sku,
            p.image_url,
            p.stock_quantity,
            p.min_stock_level,
            p.preparation_time,
            p.grams,
            p.calories,
            p.allergens,
            p.views,
            p.likes,
            p.is_active,
            p.created_at,
            s.name as subcategory_name,
            s.slug as subcategory_slug,
            c.name as category_name,
            c.is_active as category_active,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as review_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
        $whereClause
        GROUP BY p.id
        ORDER BY p.category_id, p.subcategory_id, p.name
    ");
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapeos necesarios para estructura anidada
    $categoryMap = [
        1 => 'la_ruta_11',
        2 => 'churrascos',
        3 => 'hamburguesas',
        4 => 'completos',
        5 => 'papas_y_snacks',
        6 => 'personalizar',
        7 => 'extras',
        8 => 'combos',
        12 => 'papas'
    ];

    $subcategoryMap = [
        0 => 'general',
        1 => 'tomahawks',
        2 => 'carne',
        3 => 'pollo',
        5 => 'clasicas',
        6 => 'especiales',
        7 => 'tradicionales',
        8 => 'pollo',
        9 => 'papas',
        10 => 'jugos',
        11 => 'bebidas',
        12 => 'salsas',
        26 => 'empanadas',
        27 => 'café',
        28 => 'té',
        29 => 'personalizar',
        30 => 'extras',
        31 => 'hamburguesas',
        46 => 'completos',
        47 => 'especiales',
        48 => 'salchichas',
        49 => 'lomito',
        50 => 'tomahawk',
        51 => 'lomo_vetado',
        52 => 'churrasco',
        57 => 'papas',
        59 => 'hipocaloricos',
        60 => 'pizzas'
    ];
    
    // Crear estructura anidada
    $menuData = [];
    foreach ($products as $product) {
        $catName = $categoryMap[$product['category_id']] ?? 'otros';
        $subName = $subcategoryMap[$product['subcategory_id']] ?? 'general';
        
        if (!isset($menuData[$catName])) {
            $menuData[$catName] = [];
        }
        if (!isset($menuData[$catName][$subName])) {
            $menuData[$catName][$subName] = [];
        }
        
        $menuData[$catName][$subName][] = [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'price' => (int)$product['price'],
            'image' => $product['image_url'] ?: 'https://laruta11-images.s3.amazonaws.com/menu/default-product.jpg',
            'description' => $product['description'] ?: 'Delicioso producto de La Ruta 11',
            'grams' => (int)($product['grams'] ?: 300),
            'reviews' => [
                'count' => (int)$product['review_count'],
                'average' => round($product['avg_rating'], 1)
            ],
            'views' => (int)($product['views'] ?: 0),
            'likes' => (int)($product['likes'] ?: 0),
            'category_id' => (int)$product['category_id'],
            'subcategory_id' => (int)$product['subcategory_id'],
            'subcategory_name' => $product['subcategory_name'],
            'active' => (int)$product['is_active'],
            'category_name' => $product['category_id'] == 8 ? 'Combos' : null
        ];
    }
    
    // Asegurar que personalizar tenga datos
    if (!isset($menuData['personalizar'])) {
        $menuData['personalizar'] = [];
    }
    if (!isset($menuData['personalizar']['personalizar'])) {
        $menuData['personalizar']['personalizar'] = [];
    }
    
    echo json_encode([
        'success' => true,
        'menuData' => $menuData
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>