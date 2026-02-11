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
    
    // Verificar si la columna subcategory ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM productos LIKE 'subcategory'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Agregar la columna subcategory
        $pdo->exec("ALTER TABLE productos ADD COLUMN subcategory VARCHAR(50) NULL AFTER category_id");
        echo json_encode([
            'success' => true, 
            'message' => 'Columna subcategory agregada exitosamente',
            'action' => 'added'
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'La columna subcategory ya existe',
            'action' => 'exists'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>