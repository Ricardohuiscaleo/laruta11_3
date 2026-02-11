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
    
    // Expandir campo image_url de varchar(255) a TEXT
    $sql = "ALTER TABLE products MODIFY COLUMN image_url TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL";
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Campo image_url expandido de varchar(255) a TEXT',
        'database' => $config['app_db_name']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error expandiendo campo: ' . $e->getMessage()
    ]);
}
?>