<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php hasta 5 niveles
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if ($configPath) {
    $config = include $configPath;
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

try {
    // Eliminar items incorrectos (productos 14 y 13 que están en todos los combos)
    $stmt = $pdo->prepare("DELETE FROM combo_items WHERE product_id IN (14, 13)");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    // Actualizar precios de bebidas a 0.00 para items incluidos
    $stmt = $pdo->prepare("UPDATE combo_selections SET additional_price = 0.00 WHERE selection_group = 'Bebidas'");
    $stmt->execute();
    $updated = $stmt->rowCount();
    
    echo json_encode([
        'success' => true, 
        'message' => "Items incorrectos eliminados y precios corregidos",
        'items_deleted' => $deleted,
        'prices_updated' => $updated
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>