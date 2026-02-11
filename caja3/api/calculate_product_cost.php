<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
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
        echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
        exit;
    }
    
    // Crear conexión PDO con credenciales de app
    $host = $config['app_db_host'];
    $dbname = $config['app_db_name'];
    $username = $config['app_db_user'];
    $password = $config['app_db_pass'];
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $product_id = $_GET['product_id'] ?? $_POST['product_id'] ?? null;
    
    if (!$product_id) {
        throw new Exception('Product ID requerido');
    }
    
    // Obtener receta del producto con unidad del ingrediente
    $stmt = $pdo->prepare("
        SELECT pr.*, i.cost_per_unit, i.name as ingredient_name, i.unit as ingredient_unit
        FROM product_recipes pr
        JOIN ingredients i ON pr.ingredient_id = i.id
        WHERE pr.product_id = ?
    ");
    $stmt->execute([$product_id]);
    $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recipe)) {
        echo json_encode([
            'success' => true,
            'total_cost' => 0,
            'message' => 'No hay receta para este producto',
            'ingredients_count' => 0
        ]);
        exit;
    }
    
    $total_cost = 0;
    $ingredients_detail = [];
    
    foreach ($recipe as $item) {
        $quantity = $item['quantity'];
        $recipe_unit = $item['unit'];
        $ingredient_unit = $item['ingredient_unit'];
        $cost_per_unit = $item['cost_per_unit'] ?? 0;
        
        // Calcular costo según la unidad del ingrediente
        $ingredient_cost = 0;
        
        if ($ingredient_unit === 'unidad') {
            // Si el ingrediente se vende por unidad, usar directamente
            $ingredient_cost = $quantity * $cost_per_unit;
        } else {
            // Para kg, litro, etc. convertir a la unidad base
            $converted_amount = $quantity;
            if ($recipe_unit === 'kg' && $ingredient_unit === 'kg') {
                $converted_amount = $quantity;
            } elseif ($recipe_unit === 'g' && $ingredient_unit === 'kg') {
                $converted_amount = $quantity / 1000;
            } elseif ($recipe_unit === 'ml' && $ingredient_unit === 'litro') {
                $converted_amount = $quantity / 1000;
            } elseif ($recipe_unit === 'l' && $ingredient_unit === 'litro') {
                $converted_amount = $quantity;
            } elseif ($recipe_unit === 'cucharada') {
                $converted_amount = ($quantity * 15) / 1000; // 15g por cucharada, convertir a kg
            } elseif ($recipe_unit === 'taza') {
                $converted_amount = ($quantity * 240) / 1000; // 240ml por taza, convertir a kg/litro
            } else {
                $converted_amount = $quantity / 1000; // Por defecto asumir gramos a kg
            }
            
            $ingredient_cost = $converted_amount * $cost_per_unit;
        }
        
        $total_cost += $ingredient_cost;
        
        $ingredients_detail[] = [
            'name' => $item['ingredient_name'],
            'quantity' => $quantity,
            'unit' => $recipe_unit,
            'ingredient_unit' => $ingredient_unit,
            'cost_per_unit' => $cost_per_unit,
            'ingredient_cost' => round($ingredient_cost, 2)
        ];
    }
    
    // Actualizar costo del producto automáticamente
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
        $update_stmt = $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
        $update_stmt->execute([round($total_cost, 2), $product_id]);
    }
    
    echo json_encode([
        'success' => true,
        'total_cost' => round($total_cost, 2),
        'ingredients_count' => count($recipe),
        'ingredients_detail' => $ingredients_detail,
        'message' => 'Costo calculado correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>