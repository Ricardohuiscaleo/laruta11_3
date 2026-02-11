<?php
header('Content-Type: application/json');

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión a BD']));
}

mysqli_set_charset($conn, 'utf8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$application_id = $_POST['application_id'] ?? '';
$time_elapsed = intval($_POST['time_elapsed'] ?? 0);

if (empty($application_id)) {
    echo json_encode(['success' => false, 'error' => 'ID de aplicación requerido']);
    exit();
}

try {
    // Actualizar tiempo transcurrido
    $stmt = mysqli_prepare($conn, "UPDATE job_applications SET time_elapsed = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "is", $time_elapsed, $application_id);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>