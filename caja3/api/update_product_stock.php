<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

function findConfig() {
    foreach (['', '../', '../../', '../../../'] as $l) {
        $p = __DIR__ . '/' . $l . 'config.php';
        if (file_exists($p)) return $p;
    }
    return null;
}

$config = include findConfig();
try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    if (!$id) throw new Exception('ID requerido');

    $pdo->prepare("UPDATE products SET stock_quantity=?, min_stock_level=?, is_active=? WHERE id=?")
        ->execute([$input['current_stock'], $input['min_stock_level'], $input['is_active'] ?? 1, $id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
