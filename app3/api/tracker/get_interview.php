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

if (!isset($_GET['candidate_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID de candidato requerido']);
    exit();
}

$candidateId = $_GET['candidate_id'];

try {
    $query = "SELECT * FROM interviews WHERE candidate_id = ? ORDER BY updated_at DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $candidateId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Decodificar JSON
        $row['yes_no_answers'] = json_decode($row['yes_no_answers'], true) ?: [];
        $row['open_answers'] = json_decode($row['open_answers'], true) ?: [];
        
        echo json_encode([
            'success' => true,
            'data' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontró entrevista para este candidato'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>