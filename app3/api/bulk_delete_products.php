<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',        // 1 nivel
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../config.php',  // 3 niveles  
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

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

try {
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'error' => 'IDs requeridos']);
        exit;
    }
    
    $ids = array_filter($ids, 'is_numeric');
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'IDs invalidos']);
        exit;
    }
    
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
    
    if ($stmt->execute($ids)) {
        $affected = $stmt->rowCount();
        echo json_encode([
            'success' => true, 
            'message' => 'Productos eliminados',
            'affected_rows' => $affected
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
?>