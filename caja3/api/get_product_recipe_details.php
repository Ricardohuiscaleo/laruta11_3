<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $product_id = $_GET['id'] ?? null;
    
    if (!$product_id) {
        throw new Exception('Product ID requerido');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar si tiene receta
    $check_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM product_recipes 
        WHERE product_id = ?
    ");
    $check_stmt->execute([$product_id]);
    $has_recipe = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    $ingredients = [];
    
    if ($has_recipe) {
        // Obtener TODOS los ingredientes de la receta, incluso con stock 0
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(i.id, pr.ingredient_id) as id,
                COALESCE(i.name, CONCAT('Ingrediente ID: ', pr.ingredient_id)) as name,
                COALESCE(i.current_stock, 0) as current_stock,
                COALESCE(i.unit, pr.unit) as unit,
                COALESCE(i.min_stock_level, 0) as min_stock_level,
                pr.quantity as recipe_quantity,
                pr.unit as recipe_unit
            FROM product_recipes pr
            LEFT JOIN ingredients i ON pr.ingredient_id = i.id
            WHERE pr.product_id = ?
            ORDER BY 
                CASE 
                    WHEN COALESCE(i.current_stock, 0) <= 0 THEN 0
                    WHEN COALESCE(i.current_stock, 0) <= COALESCE(i.min_stock_level, 0) THEN 1
                    ELSE 2
                END,
                i.name
        ");
        $stmt->execute([$product_id]);
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'has_recipe' => $has_recipe,
        'ingredients' => $ingredients
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'has_recipe' => false,
        'ingredients' => []
    ]);
}
?>
