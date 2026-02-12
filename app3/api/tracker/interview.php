<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

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
    echo json_encode(['success' => false, 'error' => 'Conexión fallida']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        $candidate_id = $_GET['candidate_id'] ?? $_GET['id'];
        if ($candidate_id) {
            $position = $_GET['position'] ?? 'cajero';
            
            // Obtener datos del candidato
            $stmt_candidate = mysqli_prepare($conn, "SELECT * FROM job_applications WHERE user_id = ?");
            if (!$stmt_candidate) {
                echo json_encode(['success' => false, 'error' => 'Error preparando consulta: ' . mysqli_error($conn)]);
                break;
            }
            
            mysqli_stmt_bind_param($stmt_candidate, 'i', $candidate_id);
            if (!mysqli_stmt_execute($stmt_candidate)) {
                echo json_encode(['success' => false, 'error' => 'Error ejecutando consulta: ' . mysqli_error($conn)]);
                break;
            }
            
            $result_candidate = mysqli_stmt_get_result($stmt_candidate);
            $candidate = mysqli_fetch_assoc($result_candidate);
            
            if (!$candidate) {
                echo json_encode(['success' => false, 'error' => "Candidato no encontrado user_id: $candidate_id"]);
                break;
            }
            
            // Buscar entrevista existente
            $stmt = mysqli_prepare($conn, "SELECT * FROM interviews WHERE candidate_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $candidate_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $interview = mysqli_fetch_assoc($result);
            
            // Si no existe, crear registro
            if (!$interview) {
                $stmt_insert = mysqli_prepare($conn, "INSERT INTO interviews (candidate_id, position, status, interview_date) VALUES (?, ?, 'draft', NOW())");
                if ($stmt_insert) {
                    mysqli_stmt_bind_param($stmt_insert, 'is', $candidate_id, $candidate['position']);
                    mysqli_stmt_execute($stmt_insert);
                    
                    // Obtener el registro recién creado
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $interview = mysqli_fetch_assoc($result);
                }
            }
            
            // Obtener preguntas para la posición
            $stmt2 = mysqli_prepare($conn, "SELECT * FROM interview_questions WHERE position = ? AND is_active = 1 ORDER BY question_type, question_order, id");
            mysqli_stmt_bind_param($stmt2, 's', $candidate['position']);
            mysqli_stmt_execute($stmt2);
            $result2 = mysqli_stmt_get_result($stmt2);
            $questions = mysqli_fetch_all($result2, MYSQLI_ASSOC);
            
            $data = [
                'candidate' => $candidate,
                'interview' => $interview,
                'questions' => $questions,
                'yes_no_answers' => json_decode($interview['yes_no_answers'] ?? '{}', true),
                'open_answers' => json_decode($interview['open_answers'] ?? '{}', true)
            ];
            
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID de candidato requerido']);
        }
        break;
        
    case 'POST':
        // Guardar respuestas de entrevista
        $data = json_decode(file_get_contents('php://input'), true);
        
        $yes_no_json = json_encode($data['yes_no_answers'] ?? []);
        $open_json = json_encode($data['open_answers'] ?? []);
        
        $stmt = mysqli_prepare($conn, "UPDATE interviews SET yes_no_answers = ?, open_answers = ?, notes = ?, status = 'completed' WHERE candidate_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $yes_no_json, $open_json, $data['notes'], $data['candidate_id']);
        $result = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $result]);
        break;
}
?>