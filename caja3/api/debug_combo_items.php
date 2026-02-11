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
    // Obtener todos los combos
    $stmt = $pdo->query("SELECT id, name FROM combos WHERE active = 1");
    $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    
    foreach ($combos as $combo) {
        // Items fijos del combo
        $stmt_items = $pdo->prepare("
            SELECT ci.*, p.name as product_name 
            FROM combo_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            WHERE ci.combo_id = ?
        ");
        $stmt_items->execute([$combo['id']]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        $result[] = [
            'combo_id' => $combo['id'],
            'combo_name' => $combo['name'],
            'items' => $items
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>