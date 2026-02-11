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
    
    // Crear tabla subcategories
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subcategories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            UNIQUE KEY unique_category_slug (category_id, slug)
        )
    ");
    
    // Insertar subcategorías basadas en el frontend
    $subcategories = [
        // La Ruta 11 (category_id = 1)
        ['category_id' => 1, 'name' => 'Tomahawks', 'slug' => 'tomahawks'],
        
        // Sandwiches (category_id = 2) 
        ['category_id' => 2, 'name' => 'Carne', 'slug' => 'carne'],
        ['category_id' => 2, 'name' => 'Pollo', 'slug' => 'pollo'],
        ['category_id' => 2, 'name' => 'Vegetariano', 'slug' => 'vegetariano'],
        
        // Hamburguesas (category_id = 3)
        ['category_id' => 3, 'name' => 'Clásicas', 'slug' => 'clasicas'],
        ['category_id' => 3, 'name' => 'Especiales', 'slug' => 'especiales'],
        
        // Completos (category_id = 4)
        ['category_id' => 4, 'name' => 'Tradicionales', 'slug' => 'tradicionales'],
        ['category_id' => 4, 'name' => 'Al Vapor', 'slug' => 'al_vapor'],
        
        // Snacks (category_id = 5)
        ['category_id' => 5, 'name' => 'Papas', 'slug' => 'papas'],
        ['category_id' => 5, 'name' => 'Jugos', 'slug' => 'jugos'],
        ['category_id' => 5, 'name' => 'Bebidas', 'slug' => 'bebidas'],
        ['category_id' => 5, 'name' => 'Salsas', 'slug' => 'salsas']
    ];
    
    $insertedCount = 0;
    foreach ($subcategories as $sub) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO subcategories (category_id, name, slug) VALUES (?, ?, ?)");
            $stmt->execute([$sub['category_id'], $sub['name'], $sub['slug']]);
            if ($stmt->rowCount() > 0) {
                $insertedCount++;
            }
        } catch (PDOException $e) {
            // Ignorar duplicados
        }
    }
    
    // Agregar columna subcategory_id a products si no existe
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'subcategory_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN subcategory_id INT NULL AFTER category_id");
        $pdo->exec("ALTER TABLE products ADD FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabla subcategories creada y configurada',
        'subcategories_inserted' => $insertedCount,
        'total_subcategories' => count($subcategories)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>