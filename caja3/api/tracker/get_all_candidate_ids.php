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
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

try {
    // Obtener todos los user_ids únicos
    $query = "SELECT DISTINCT user_id FROM job_applications WHERE user_id IS NOT NULL";
    $result = mysqli_query($conn, $query);
    
    $ids = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = $row['user_id'];
    }
    
    echo json_encode([
        'success' => true,
        'ids' => $ids
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>