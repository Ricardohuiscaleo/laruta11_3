<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

// Conectar a BD
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

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Estadísticas generales de entrevistas
    $statsQuery = "
        SELECT 
            COUNT(*) as total_interviews,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_interviews,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_interviews,
            SUM(CASE WHEN status = 'callback_scheduled' THEN 1 ELSE 0 END) as callback_interviews
        FROM interviews
    ";
    
    $result = mysqli_query($conn, $statsQuery);
    $stats = mysqli_fetch_assoc($result);
    
    // Estadísticas por posición
    $positionQuery = "
        SELECT 
            position,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM interviews 
        GROUP BY position
    ";
    
    $result = mysqli_query($conn, $positionQuery);
    $positionStats = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $positionStats[] = $row;
    }
    
    // Entrevistas recientes (últimas 7 días)
    $recentQuery = "
        SELECT 
            DATE(interview_date) as date,
            COUNT(*) as count
        FROM interviews 
        WHERE interview_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(interview_date)
        ORDER BY date DESC
    ";
    
    $result = mysqli_query($conn, $recentQuery);
    $recentStats = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $recentStats[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'general' => $stats,
            'by_position' => $positionStats,
            'recent' => $recentStats
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>