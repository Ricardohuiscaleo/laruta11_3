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
    $query = "SELECT candidate_id, status, interview_date FROM interviews ORDER BY updated_at DESC";
    $result = mysqli_query($conn, $query);
    
    $interviews = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $interviews[$row['candidate_id']] = [
            'status' => $row['status'],
            'interview_date' => $row['interview_date']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $interviews
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>