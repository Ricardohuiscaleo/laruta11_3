<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$config = null;
foreach ([__DIR__.'/../../config.php', __DIR__.'/../../../config.php'] as $p) {
    if (file_exists($p)) { $config = require_once $p; break; }
}
if (!$config) { echo json_encode(['success'=>false,'error'=>'Config no encontrado']); exit; }

try {
    if (!isset($_FILES['photo']) || !isset($_POST['order_id'])) {
        throw new Exception('photo y order_id requeridos');
    }

    $s3Manager = null;
    foreach ([__DIR__.'/../S3Manager.php', __DIR__.'/../../S3Manager.php'] as $p) {
        if (file_exists($p)) { require_once $p; $s3Manager = new S3Manager(); break; }
    }
    if (!$s3Manager) throw new Exception('S3Manager no encontrado');

    $orderId = intval($_POST['order_id']);
    $fileName = 'despacho/pedido_' . $orderId . '_' . time() . '.jpg';
    $url = $s3Manager->uploadFile($_FILES['photo'], $fileName);

    $pdo = new PDO(
        "mysql:host={$config['ruta11_db_host']};dbname={$config['ruta11_db_name']};charset=utf8mb4",
        $config['ruta11_db_user'], $config['ruta11_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->prepare("UPDATE tuu_orders SET dispatch_photo_url = ? WHERE id = ?")->execute([$url, $orderId]);

    echo json_encode(['success' => true, 'url' => $url]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
