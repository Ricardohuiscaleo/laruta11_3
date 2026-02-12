<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
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
        echo json_encode([]);
        exit;
    }
    
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    
    if (!$product_id) {
        echo json_encode([]);
        exit;
    }
    
    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }
    
    $sql = "SELECT pr.ingredient_id, pr.quantity, pr.unit, i.name, i.cost_per_unit, i.unit as ingredient_unit 
            FROM product_recipes pr 
            LEFT JOIN ingredients i ON pr.ingredient_id = i.id 
            WHERE pr.product_id = ?
            ORDER BY i.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recipe = [];
    while ($row = $result->fetch_assoc()) {
        $quantity = floatval($row['quantity']);
        $cost_per_unit = floatval($row['cost_per_unit']);
        $ingredient_unit = $row['ingredient_unit'];
        $recipe_unit = $row['unit'] ?: 'g';
        
        // Calcular costo según la unidad del ingrediente
        $estimated_cost = 0;
        
        if ($ingredient_unit === 'unidad') {
            // Si el ingrediente se vende por unidad, usar directamente
            $estimated_cost = $quantity * $cost_per_unit;
        } else {
            // Para kg, litro, etc. convertir a la unidad base
            $converted_amount = $quantity;
            if ($recipe_unit === 'kg' && $ingredient_unit === 'kg') {
                $converted_amount = $quantity;
            } else if ($recipe_unit === 'g' && $ingredient_unit === 'kg') {
                $converted_amount = $quantity / 1000;
            } else if ($recipe_unit === 'ml' && $ingredient_unit === 'litro') {
                $converted_amount = $quantity / 1000;
            } else if ($recipe_unit === 'l' && $ingredient_unit === 'litro') {
                $converted_amount = $quantity;
            } else if ($recipe_unit === 'cucharada') {
                $converted_amount = ($quantity * 15) / 1000; // 15g por cucharada, convertir a kg
            } else if ($recipe_unit === 'taza') {
                $converted_amount = ($quantity * 240) / 1000; // 240ml por taza, convertir a kg/litro
            } else {
                $converted_amount = $quantity / 1000; // Por defecto asumir gramos a kg
            }
            
            $estimated_cost = $converted_amount * $cost_per_unit;
        }
        
        $recipe[] = [
            "ingredient_id" => intval($row['ingredient_id']),
            "name" => $row['name'] ?: 'Ingrediente desconocido',
            "quantity" => $quantity,
            "unit" => $recipe_unit,
            "estimated_cost" => $estimated_cost
        ];
    }
    
    echo json_encode($recipe);
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>