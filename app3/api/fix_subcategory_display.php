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
    
    // Verificar si existe la tabla subcategories
    $stmt = $pdo->query("SHOW TABLES LIKE 'subcategories'");
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'error' => 'Tabla subcategories no existe. Ejecuta setup_subcategories.php primero']);
        exit;
    }
    
    // Obtener productos con subcategorías
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.category_id, p.subcategory_id, s.name as subcategory_name 
        FROM products p 
        LEFT JOIN subcategories s ON p.subcategory_id = s.id 
        ORDER BY p.id ASC
    ");
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categoryNames = [
        1 => 'La Ruta 11',
        2 => 'Churrascos', 
        3 => 'Hamburguesas',
        4 => 'Completos',
        5 => 'Papas y Snacks'
    ];
    
    $result = [];
    foreach ($products as $product) {
        $result[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'category_id' => $product['category_id'],
            'category_name' => $categoryNames[$product['category_id']] ?? 'Sin categoría',
            'subcategory_id' => $product['subcategory_id'],
            'subcategory_name' => $product['subcategory_name'] ?? 'Sin subcategoría',
            'display_category' => $product['subcategory_name'] ?? $categoryNames[$product['category_id']] ?? 'Sin categoría'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $result,
        'total' => count($result)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>