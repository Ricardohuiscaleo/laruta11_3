<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderNumber = $input['order_number'] ?? '';

if (!$orderNumber) {
    echo json_encode(['success' => false, 'error' => 'Número de orden requerido']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Eliminar orden y sus items relacionados
    $pdo->beginTransaction();
    
    // Eliminar items de la orden
    $sql = "DELETE FROM tuu_order_items WHERE order_number = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderNumber]);
    
    // Eliminar orden
    $sql = "DELETE FROM tuu_orders WHERE order_number = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderNumber]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
