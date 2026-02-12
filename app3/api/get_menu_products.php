<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
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
    $pdo = require_once __DIR__ . '/db_connect.php';

    // Obtener todos los productos activos con estadísticas de reseñas y subcategorías
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
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(r.id) as review_count
        FROM products p
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
        WHERE p.is_active = 1 
        GROUP BY p.id
        ORDER BY p.category_id, p.subcategory_id, p.name
    ");
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapear categorías
    $categoryMap = [
        1 => 'la_ruta_11',
        2 => 'churrascos', 
        3 => 'hamburguesas',
        4 => 'completos',
        5 => 'papas_y_snacks',
        6 => 'personalizar',
        7 => 'extras',
        8 => 'Combos', // Combos tiene su propia categoría
        12 => 'papas' // Nueva categoría Papas
    ];
    
    // Mapeo de subcategorías de BD a frontend
    $subcategoryMap = [
        'Tomahawks' => 'tomahawks',
        'Carne' => 'carne',
        'Pollo' => 'pollo', 
        'Vegetariano' => 'vegetariano',
        'Salchicas' => 'salchicas',
        'Lomito' => 'lomito',
        'Tomahawk' => 'tomahawk',
        'Lomo Vetado' => 'lomo vetado',
        'Churrasco' => 'churrasco',
        'Clásicas' => 'clasicas',
        'Especiales' => 'especiales',
        'Tradicionales' => 'tradicionales',
        'Al Vapor' => 'al vapor',
        'Papas' => 'papas',
        'Jugos' => 'jugos',
        'Bebidas' => 'bebidas',
        'Salsas' => 'salsas',
        'Empanadas' => 'empanadas',
        'Saludables' => 'saludables',
        'Hipocalóricos' => 'hipocalóricos',
        'Café' => 'café',
        'Té' => 'té',
        // Subcategorías de Combos
        'Hamburguesas' => 'hamburguesas', // Para categoría Combos
        // amazonq-ignore-next-line
        'Sándwiches' => 'Sándwiches', // Mapear Churrascos a sandwiches para Combos
        'Completos' => 'completos'
    ];
    
    // Organizar productos por categoría
    $menuData = [
        'la_ruta_11' => ['tomahawks' => []],
        'churrascos' => ['pollo' => [], 'salchicas' => [], 'lomito' => [], 'tomahawk' => [], 'lomo vetado' => [], 'churrasco' => []],
        'hamburguesas' => ['clasicas' => [], 'especiales' => []],
        // amazonq-ignore-next-line
        'completos' => ['tradicionales' => [], 'especiales' => [], 'al vapor' => []],
        'papas_y_snacks' => [
            'papas' => [], 'empanadas' => [], 'jugos' => [], 'bebidas' => [], 'salsas' => [], 'saludables' => [], 'hipocalóricos' => [], 'café' => [], 'té' => []
        ],
        'papas' => [
            'papas' => [], 'empanadas' => [], 'jugos' => [], 'bebidas' => [], 'salsas' => [], 'café' => [], 'té' => []
        ],
        'Combos' => [
            'hamburguesas' => [], 'Sándwiches' => [], 'completos' => []
        ],
        'personalizar' => ['personalizar' => []],
        'extras' => ['extras' => []]
    ];
    
    foreach ($products as $product) {
        $categoryKey = $categoryMap[$product['category_id']] ?? 'papas_y_snacks';
        
        // Si es categoría papas (12), también agregarlo a papas_y_snacks
        if ($product['category_id'] == 12) {
            $categoryKey = 'papas_y_snacks';
        }
        
        // Formatear producto
        $formattedProduct = [
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
            'subcategory_id' => $product['subcategory_id'] ? (int)$product['subcategory_id'] : null,
            'subcategory_name' => $product['subcategory_name'],
            'query' => strtolower($product['name']) // Para búsquedas
        ];
        

        
        // Agregar información de categoría para identificación
        $formattedProduct['category_name'] = $categoryKey === 'Combos' ? 'Combos' : null;
        
        // Determinar subcategoría usando la base de datos
        $subcategorySlug = 'tomahawks'; // Default
        
        if ($product['subcategory_name']) {
            $subcategorySlug = $subcategoryMap[$product['subcategory_name']] ?? strtolower($product['subcategory_name']);
        } else {
            // Fallback por categoría
            switch ($categoryKey) {
                case 'la_ruta_11':
                    $subcategorySlug = 'tomahawks';
                    break;
                case 'churrascos':
                    $subcategorySlug = 'churrasco';
                    break;
                case 'hamburguesas':
                    $subcategorySlug = 'clasicas';
                    break;
                case 'completos':
                    $subcategorySlug = 'tradicionales';
                    break;
                case 'papas_y_snacks':
                    $subcategorySlug = 'papas';
                    break;
                case 'papas':
                    $subcategorySlug = 'papas';
                    break;
                case 'Combos':
                    $subcategorySlug = 'hamburguesas'; // Default para combos
                    break;
                case 'personalizar':
                    $subcategorySlug = 'personalizar';
                    break;
                case 'extras':
                    $subcategorySlug = 'extras';
                    break;
            }
        }
        
        // Asegurar que la subcategoría existe en la estructura
        if (isset($menuData[$categoryKey][$subcategorySlug])) {
            $menuData[$categoryKey][$subcategorySlug][] = $formattedProduct;
        } else {
            // Crear la subcategoría si no existe
            $menuData[$categoryKey][$subcategorySlug] = [$formattedProduct];
        }
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