<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Cache busting headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Verificar autenticación
if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

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
    // Obtener estadísticas de candidatos únicos
    $stats_query = "
        SELECT 
            COUNT(DISTINCT user_id) as total,
            COUNT(DISTINCT CASE WHEN status = 'completed' THEN user_id END) as completed,
            COUNT(DISTINCT CASE WHEN status = 'started' THEN user_id END) as pending,
            ROUND(AVG(CASE WHEN score > 0 THEN score ELSE NULL END), 1) as average_score
        FROM job_applications
    ";
    
    $result = mysqli_query($conn, $stats_query);
    $stats = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => intval($stats['total']),
            'completed' => intval($stats['completed']),
            'pending' => intval($stats['pending']),
            'average_score' => floatval($stats['average_score']) ?: 0
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>