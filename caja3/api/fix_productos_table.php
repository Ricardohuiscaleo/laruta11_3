<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    
    // Verificar si la columna description existe
    $stmt = $pdo->query("SHOW COLUMNS FROM productos LIKE 'description'");
    
    if ($stmt->rowCount() == 0) {
        // Agregar la columna description
        $pdo->exec("ALTER TABLE productos ADD COLUMN description TEXT NULL AFTER name");
        echo json_encode(['success' => true, 'message' => 'Columna description agregada correctamente']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Columna description ya existe']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>