<?php
header('Content-Type: application/json');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
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
    die(json_encode(['success' => false, 'error' => 'Config file not found']));
}

try {
    // Crear conexión usando la configuración de app
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tabla subcategories si no existe
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS subcategories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category_id (category_id)
        )
    ";
    
    $pdo->exec($createTableSQL);
    
    // Buscar el ID de la categoría "Combos"
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = 'Combos' LIMIT 1");
    $stmt->execute();
    $combosCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$combosCategory) {
        echo json_encode(['success' => false, 'error' => 'Categoría Combos no encontrada']);
        exit;
    }
    
    $combos_category_id = $combosCategory['id'];
    
    // Verificar si ya existen subcategorías para Combos
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subcategories WHERE category_id = ?");
    $stmt->execute([$combos_category_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing['count'] > 0) {
        echo json_encode(['success' => true, 'message' => 'Las subcategorías de Combos ya existen', 'category_id' => $combos_category_id]);
        exit;
    }
    
    // Insertar subcategorías para Combos
    $subcategories = [
        ['name' => 'Hamburguesas', 'description' => 'Combos con hamburguesas', 'sort_order' => 1],
        ['name' => 'Churrascos', 'description' => 'Combos con churrascos', 'sort_order' => 2],
        ['name' => 'Completos', 'description' => 'Combos con completos', 'sort_order' => 3]
    ];
    
    $insertSQL = "INSERT INTO subcategories (category_id, name, description, sort_order, is_active) VALUES (?, ?, ?, ?, 1)";
    $stmt = $pdo->prepare($insertSQL);
    
    $inserted = 0;
    foreach ($subcategories as $sub) {
        $stmt->execute([$combos_category_id, $sub['name'], $sub['description'], $sub['sort_order']]);
        $inserted++;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Se crearon $inserted subcategorías para Combos",
        'category_id' => $combos_category_id,
        'subcategories_created' => $inserted
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>