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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = $input['product_id'] ?? null;

    if (!$product_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de producto requerido']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE products SET likes = likes + 1 WHERE id = ?");
    $stmt->execute([$product_id]);
    
    $stmt = $pdo->prepare("SELECT likes FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'likes' => $result['likes']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al actualizar likes']);
}
?>