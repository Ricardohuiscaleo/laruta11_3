<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
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
    
    // Crear tabla de recetas si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_recipes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            quantity_needed DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
            UNIQUE KEY unique_recipe (product_id, ingredient_id)
        )
    ");
    
    // Obtener productos
    $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($productos as &$producto) {
        // Verificar si tiene receta (ingredientes)
        $recipe_stmt = $pdo->prepare("
            SELECT pr.quantity, i.current_stock, i.name as ingredient_name
            FROM product_recipes pr 
            JOIN ingredients i ON pr.ingredient_id = i.id 
            WHERE pr.product_id = ? AND i.is_active = 1
        ");
        $recipe_stmt->execute([$producto['id']]);
        $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($recipe)) {
            // Producto simple (bebida) - usar stock directo
            $producto['calculated_stock'] = $producto['stock_quantity'];
            $producto['stock_type'] = 'simple';
        } else {
            // Producto preparado - calcular stock basado en ingredientes
            $min_possible = PHP_INT_MAX;
            
            foreach ($recipe as $ingredient) {
                $possible_units = floor($ingredient['current_stock'] / $ingredient['quantity']);
                $min_possible = min($min_possible, $possible_units);
            }
            
            $producto['calculated_stock'] = max(0, $min_possible);
            $producto['stock_type'] = 'prepared';
            $producto['recipe'] = $recipe;
        }
    }
    
    echo json_encode($productos);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>