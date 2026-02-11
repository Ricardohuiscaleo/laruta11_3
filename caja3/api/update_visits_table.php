<?php
header('Content-Type: application/json');

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

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Agregar nuevas columnas
    $columns_to_add = [
        "ADD COLUMN latitude DECIMAL(10, 8)",
        "ADD COLUMN longitude DECIMAL(11, 8)", 
        "ADD COLUMN screen_resolution VARCHAR(20)",
        "ADD COLUMN viewport_size VARCHAR(20)",
        "ADD COLUMN timezone VARCHAR(50)",
        "ADD COLUMN language VARCHAR(10)",
        "ADD COLUMN platform VARCHAR(50)",
        "ADD COLUMN full_address TEXT"
    ];
    
    $added = [];
    $errors = [];
    
    foreach ($columns_to_add as $column) {
        try {
            $pdo->exec("ALTER TABLE site_visits $column");
            $added[] = $column;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                $errors[] = $e->getMessage();
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'added_columns' => $added,
        'errors' => $errors,
        'message' => 'Table structure updated'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>