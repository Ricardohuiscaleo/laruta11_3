<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Config not found']));
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['categories']) || !is_array($input['categories'])) {
        throw new Exception('Invalid input');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $pdo->beginTransaction();
    
    $sql = "UPDATE product_categories SET is_active = :is_active WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    foreach ($input['categories'] as $cat) {
        $stmt->execute([
            'is_active' => $cat['is_active'] ? 1 : 0,
            'id' => $cat['id']
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Categorías actualizadas correctamente'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
