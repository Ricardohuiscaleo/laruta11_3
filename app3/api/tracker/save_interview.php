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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['candidate_id']) || !isset($input['position'])) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
    exit();
}

// Crear tabla si no existe
$createTable = "
CREATE TABLE IF NOT EXISTS interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    interview_date DATETIME NOT NULL,
    status ENUM('draft', 'completed', 'callback_scheduled') DEFAULT 'draft',
    yes_no_answers JSON,
    open_answers JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_candidate (candidate_id),
    INDEX idx_status (status)
)";

mysqli_query($conn, $createTable);

try {
    // Verificar si ya existe una entrevista para este candidato
    $checkQuery = "SELECT id FROM interviews WHERE candidate_id = ?";
    $stmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($stmt, "s", $input['candidate_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $yesNoAnswers = json_encode($input['yes_no_answers'] ?? []);
    $openAnswers = json_encode($input['open_answers'] ?? []);
    $notes = $input['notes'] ?? '';
    $status = $input['status'] ?? 'draft';
    $interviewDate = $input['interview_date'] ?? date('Y-m-d H:i:s');
    
    if (mysqli_fetch_assoc($result)) {
        // Actualizar entrevista existente
        $updateQuery = "UPDATE interviews SET 
                       position = ?, 
                       interview_date = ?, 
                       status = ?, 
                       yes_no_answers = ?, 
                       open_answers = ?, 
                       notes = ?,
                       updated_at = CURRENT_TIMESTAMP
                       WHERE candidate_id = ?";
        
        $stmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($stmt, "sssssss", 
            $input['position'], 
            $interviewDate, 
            $status, 
            $yesNoAnswers, 
            $openAnswers, 
            $notes, 
            $input['candidate_id']
        );
    } else {
        // Crear nueva entrevista
        $insertQuery = "INSERT INTO interviews (candidate_id, position, interview_date, status, yes_no_answers, open_answers, notes) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $insertQuery);
        mysqli_stmt_bind_param($stmt, "sssssss", 
            $input['candidate_id'], 
            $input['position'], 
            $interviewDate, 
            $status, 
            $yesNoAnswers, 
            $openAnswers, 
            $notes
        );
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Entrevista guardada correctamente',
            'data' => [
                'candidate_id' => $input['candidate_id'],
                'status' => $status,
                'interview_date' => $interviewDate
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar entrevista: ' . mysqli_error($conn)]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>