<?php
header('Content-Type: application/json');
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Configurar conexión a BD desde config central
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

try {
    // Verificar últimas 5 aplicaciones
    $query = "SELECT id, user_id, position, nombre, score, status, detected_skills, created_at, completed_at 
              FROM job_applications 
              ORDER BY created_at DESC 
              LIMIT 5";
    
    $result = $conn->query($query);
    $applications = [];
    
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'total_applications' => count($applications),
        'applications' => $applications
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>