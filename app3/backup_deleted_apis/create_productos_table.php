<?php
header('Content-Type: application/json');

// Buscar config.php
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

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "CREATE TABLE IF NOT EXISTS productos (
        id int(11) NOT NULL AUTO_INCREMENT,
        category_id int(11) NOT NULL,
        name varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
        description text COLLATE utf8mb4_unicode_ci,
        price decimal(10,2) NOT NULL,
        cost_price decimal(10,2) DEFAULT 0.00,
        image_url varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        sku varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        stock_quantity int(11) DEFAULT 0,
        min_stock_level int(11) DEFAULT 5,
        is_active tinyint(1) DEFAULT 1,
        has_variants tinyint(1) DEFAULT 0,
        preparation_time int(11) DEFAULT 10,
        calories int(11) DEFAULT NULL,
        allergens text COLLATE utf8mb4_unicode_ci,
        created_at timestamp DEFAULT current_timestamp(),
        updated_at timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        grams int(11) DEFAULT 0,
        views int(11) DEFAULT 0,
        likes int(11) DEFAULT 0,
        PRIMARY KEY (id),
        KEY category_id (category_id),
        KEY sku (sku),
        KEY stock_quantity (stock_quantity),
        KEY is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabla productos creada exitosamente',
        'database' => $config['app_db_name']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error creando tabla: ' . $e->getMessage()
    ]);
}
?>