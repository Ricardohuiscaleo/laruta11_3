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
        echo json_encode(['success' => false, 'error' => 'Configuración no encontrada']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['product_id']) || !isset($data['ingredients'])) {
        echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
        exit;
    }
    
    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión']);
        exit;
    }
    
    $conn->begin_transaction();
    
    // Eliminar receta actual
    $sql_delete = "DELETE FROM product_recipes WHERE product_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $data['product_id']);
    $stmt_delete->execute();
    
    // Insertar nuevos ingredientes
    if (!empty($data['ingredients'])) {
        $sql_insert = "INSERT INTO product_recipes (product_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        
        foreach ($data['ingredients'] as $ingredient) {
            $ingredient_id = intval($ingredient['ingredient_id']);
            $quantity = floatval($ingredient['quantity']);
            $unit = isset($ingredient['unit']) ? $ingredient['unit'] : 'g';
            
            $stmt_insert->bind_param("iids", $data['product_id'], $ingredient_id, $quantity, $unit);
            $stmt_insert->execute();
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Receta guardada correctamente']);
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al guardar receta']);
}
?>