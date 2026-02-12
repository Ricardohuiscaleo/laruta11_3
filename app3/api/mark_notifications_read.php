<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php en múltiples niveles
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
    die(json_encode(['success' => false, 'error' => 'Config file not found']));
}

// Crear conexión usando la configuración de app
$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

// Obtener customer_name de la sesión
session_start();
$customer_name = $_SESSION['user_name'] ?? $_SESSION['nombre'] ?? null;

if (!$customer_name) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit;
}

try {
    // Marcar todas las notificaciones como leídas SOLO para este usuario
    $sql = "UPDATE order_notifications SET is_read = 1 WHERE customer_name = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $customer_name);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificaciones marcadas como leídas',
            'affected_rows' => $stmt->affected_rows
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error al actualizar notificaciones: ' . $conn->error
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>