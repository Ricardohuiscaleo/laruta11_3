<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    $updates = json_decode($_POST['updates'] ?? '{}', true);
    
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'error' => 'IDs requeridos']);
        exit;
    }
    
    if (empty($updates) || !is_array($updates)) {
        echo json_encode(['success' => false, 'error' => 'Campos a actualizar requeridos']);
        exit;
    }
    
    $ids = array_filter($ids, 'is_numeric');
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'IDs invalidos']);
        exit;
    }
    
    $allowedFields = ['price', 'cost_price', 'category_id', 'min_stock_level', 'subcategory_id'];
    $setParts = [];
    $params = [];
    
    foreach ($updates as $field => $value) {
        if (in_array($field, $allowedFields) && $value !== '') {
            $setParts[] = "$field = ?";
            $params[] = $value;
        }
    }
    
    if (empty($setParts)) {
        echo json_encode(['success' => false, 'error' => 'No hay campos validos para actualizar']);
        exit;
    }
    
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $sql = "UPDATE productos SET " . implode(', ', $setParts) . " WHERE id IN ($placeholders)";
    
    $stmt = $pdo->prepare($sql);
    $allParams = array_merge($params, $ids);
    
    if ($stmt->execute($allParams)) {
        $affected = $stmt->rowCount();
        echo json_encode([
            'success' => true, 
            'message' => 'Productos actualizados',
            'affected_rows' => $affected
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
?>