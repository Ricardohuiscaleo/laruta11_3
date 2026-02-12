<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
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

// Conexión mysqli
$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexion']);
    exit;
}

mysqli_set_charset($conn, "utf8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

try {
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    $is_active = intval($_POST['is_active'] ?? 1);
    
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
    $sql = "UPDATE products SET is_active = ? WHERE id IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    $types = 'i' . str_repeat('i', count($ids));
    $params = array_merge([$is_active], $ids);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
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

$conn->close();
?>