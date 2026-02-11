<?php
$config_paths = [
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

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Insertar interacción
    $stmt = $pdo->prepare("
        INSERT INTO user_interactions 
        (session_id, user_ip, action_type, element_type, element_id, element_text, product_id, category_id, page_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $input['session_id'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $input['action_type'] ?? 'click',
        $input['element_type'] ?? '',
        $input['element_id'] ?? '',
        $input['element_text'] ?? '',
        $input['product_id'] ?? null,
        $input['category_id'] ?? null,
        $input['page_url'] ?? ''
    ]);
    
    // Actualizar analytics de producto si aplica
    if ($input['product_id']) {
        $field = '';
        switch ($input['action_type']) {
            case 'view': $field = 'views_count'; break;
            case 'click': $field = 'clicks_count'; break;
            case 'add_to_cart': $field = 'cart_adds'; break;
            case 'remove_from_cart': $field = 'cart_removes'; break;
        }
        
        if ($field) {
            $stmt = $pdo->prepare("
                INSERT INTO product_analytics (product_id, product_name, {$field}) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE {$field} = {$field} + 1
            ");
            $stmt->execute([$input['product_id'], $input['product_name'] ?? '']);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>