<?php
header('Content-Type: application/json');

$configPaths = ['../config.php', '../../config.php', '../../../config.php', '../../../../config.php'];
$configFound = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config = require $path;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['success' => false, 'error' => 'No se pudo encontrar config.php']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Mapeo de productos a subcategorías basado en el análisis de los datos
    $productMappings = [
        // La Ruta 11 (category_id = 1) -> Tomahawks (subcategory_id = 1)
        [1, 2, 3], // Tomahawk Chedar, Tomahawk Provoleta, Tomahawk Full Ruta 11
        
        // Sandwiches (category_id = 2)
        // Carne (subcategory_id = 2): productos 4, 5
        // Pollo (subcategory_id = 3): productos 6, 7  
        // Vegetariano (subcategory_id = 4): producto 8
        
        // Hamburguesas (category_id = 3)
        // Clásicas (subcategory_id = 5): productos 9, 10
        // Especiales (subcategory_id = 6): productos 11, 12, 13
        
        // Completos (category_id = 4)
        // Tradicionales (subcategory_id = 7): productos 14, 15
        // Al Vapor (subcategory_id = 8): producto 16
        
        // Snacks (category_id = 5)
        // Papas (subcategory_id = 9): productos 17, 18, 19, 20
        // Jugos (subcategory_id = 10): productos 21, 22
        // Bebidas (subcategory_id = 11): productos 23, 24
        // Salsas (subcategory_id = 12): productos 25, 26, 27
    ];
    
    $assignments = [
        // La Ruta 11 -> Tomahawks
        [1, 1], [2, 1], [3, 1],
        
        // Sandwiches -> Carne
        [4, 2], [5, 2],
        
        // Sandwiches -> Pollo  
        [6, 3], [7, 3],
        
        // Sandwiches -> Vegetariano
        [8, 4],
        
        // Hamburguesas -> Clásicas
        [9, 5], [10, 5],
        
        // Hamburguesas -> Especiales
        [11, 6], [12, 6], [13, 6],
        
        // Completos -> Tradicionales
        [14, 7], [15, 7],
        
        // Completos -> Al Vapor
        [16, 8],
        
        // Snacks -> Papas
        [17, 9], [18, 9], [19, 9], [20, 9],
        
        // Snacks -> Jugos
        [21, 10], [22, 10],
        
        // Snacks -> Bebidas
        [23, 11], [24, 11],
        
        // Snacks -> Salsas
        [25, 12], [26, 12], [27, 12]
    ];
    
    $updatedCount = 0;
    
    foreach ($assignments as $assignment) {
        $productId = $assignment[0];
        $subcategoryId = $assignment[1];
        
        $stmt = $pdo->prepare("UPDATE products SET subcategory_id = ? WHERE id = ?");
        $stmt->execute([$subcategoryId, $productId]);
        
        if ($stmt->rowCount() > 0) {
            $updatedCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Subcategorías asignadas a productos existentes',
        'products_updated' => $updatedCount,
        'total_assignments' => count($assignments)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>