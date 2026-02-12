<?php
header('Content-Type: application/json');

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
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener ID de subcategoría bebidas
    $stmt = $pdo->prepare("SELECT id FROM subcategories WHERE category_id = 5 AND name = 'Bebidas'");
    $stmt->execute();
    $bebidas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bebidas) {
        echo json_encode(['success' => false, 'error' => 'Subcategoría Bebidas no encontrada']);
        exit;
    }
    
    $bebidasId = $bebidas['id'];

    // Actualizar todos los productos de bebidas que tienen subcategory_id NULL
    $stmt = $pdo->prepare("
        UPDATE products 
        SET subcategory_id = ? 
        WHERE category_id = 5 
        AND subcategory_id IS NULL 
        AND (
            name LIKE '%Pepsi%' OR 
            name LIKE '%Coca-Cola%' OR 
            name LIKE '%Sprite%' OR 
            name LIKE '%Fanta%' OR 
            name LIKE '%7UP%' OR 
            name LIKE '%Crush%' OR 
            name LIKE '%Kem%' OR 
            name LIKE '%Bilz%' OR 
            name LIKE '%Pap%' OR 
            name LIKE '%Limón Soda%' OR 
            name LIKE '%Agua%' OR 
            name LIKE '%Jugo%' OR 
            name LIKE '%H2OH%' OR 
            name LIKE '%Canada Dry%' OR 
            name LIKE '%Powerade%' OR 
            name LIKE '%Monster%'
        )
    ");
    
    $stmt->execute([$bebidasId]);
    $updatedCount = $stmt->rowCount();

    // Verificar productos actualizados
    $stmt = $pdo->prepare("
        SELECT id, name, subcategory_id 
        FROM products 
        WHERE category_id = 5 
        AND subcategory_id = ? 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $stmt->execute([$bebidasId]);
    $updatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => "Se actualizaron {$updatedCount} productos de bebidas",
        'bebidas_subcategory_id' => $bebidasId,
        'updated_count' => $updatedCount,
        'sample_products' => $updatedProducts
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>