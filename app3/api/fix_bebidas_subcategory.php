<?php
header('Content-Type: application/json');

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
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar si existe la subcategoría "bebidas" para la categoría 5 (papas_y_snacks)
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = 5 AND name = 'Bebidas'");
    $stmt->execute();
    $bebidas = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bebidas) {
        // Crear la subcategoría "bebidas"
        $stmt = $pdo->prepare("INSERT INTO subcategories (category_id, name, slug, is_active, sort_order) VALUES (5, 'Bebidas', 'bebidas', 1, 3)");
        $stmt->execute();
        $bebidasId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Subcategoría Bebidas creada correctamente',
            'subcategory_id' => $bebidasId
        ]);
    } else {
        // Verificar que esté activa
        if (!$bebidas['is_active']) {
            $stmt = $pdo->prepare("UPDATE subcategories SET is_active = 1 WHERE id = ?");
            $stmt->execute([$bebidas['id']]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Subcategoría Bebidas ya existe y está activa',
            'subcategory_id' => $bebidas['id']
        ]);
    }

    // Mostrar todas las subcategorías de la categoría 5
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = 5 ORDER BY sort_order, name");
    $stmt->execute();
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Subcategoría verificada/creada',
        'subcategories' => $subcategories
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>