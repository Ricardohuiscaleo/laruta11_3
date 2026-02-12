<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
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
    
    // Obtener productos con receta
    $products_stmt = $pdo->query("
        SELECT DISTINCT p.id, p.name, p.cost_price as old_cost
        FROM products p
        WHERE EXISTS (
            SELECT 1 FROM product_recipes pr WHERE pr.product_id = p.id
        )
    ");
    
    $updated = [];
    $errors = [];
    
    foreach ($products_stmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
        // Calcular costo desde receta
        $cost_stmt = $pdo->prepare("
            SELECT SUM(
                i.cost_per_unit * pr.quantity * 
                CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
            ) as recipe_cost
            FROM product_recipes pr
            JOIN ingredients i ON pr.ingredient_id = i.id
            WHERE pr.product_id = ? AND i.is_active = 1
        ");
        $cost_stmt->execute([$product['id']]);
        $result = $cost_stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_cost = $result['recipe_cost'] ?? $product['old_cost'];
        
        if ($new_cost > 0) {
            // Actualizar cost_price
            $update_stmt = $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
            $update_stmt->execute([$new_cost, $product['id']]);
            
            $updated[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'old_cost' => round($product['old_cost'], 2),
                'new_cost' => round($new_cost, 2),
                'difference' => round($new_cost - $product['old_cost'], 2)
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'updated_count' => count($updated),
        'products' => $updated,
        'message' => count($updated) . ' productos actualizados'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
