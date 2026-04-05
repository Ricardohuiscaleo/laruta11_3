<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php',
    __DIR__ . '/../../../../../../config.php'
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require $path; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id     = (int)($data['id'] ?? 0);
$status = $data['status'] ?? ''; // 'pagado' o 'cancelado'

if (!$id || !in_array($status, ['pagado', 'cancelado', 'pendiente'])) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->prepare("UPDATE tv_orders SET status = ? WHERE id = ?")->execute([$status, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
