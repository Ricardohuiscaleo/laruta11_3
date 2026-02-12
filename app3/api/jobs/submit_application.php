<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$application_id = $_POST['application_id'] ?? '';
$answers = $_POST['answers'] ?? [];
$final_score = min(100, max(0, floatval($_POST['final_score'] ?? 0))); // Limitar entre 0 y 100

if (empty($application_id) || empty($answers)) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

try {
    // Analizar texto para obtener skills
    $texto = '';
    if (is_string($answers)) {
        $answersArray = json_decode($answers, true);
    } else {
        $answersArray = $answers;
    }
    
    foreach ($answersArray as $answer) {
        $texto .= ' ' . ($answer['answer'] ?? '');
    }
    
    // Obtener skills detectadas
    $detectedSkills = analyzeTextForSkills(trim($texto));
    
    // Guardar respuestas finales
    foreach ($answersArray as $index => $answer) {
        $stmt = mysqli_prepare($conn, "INSERT INTO job_answers (application_id, question_number, question_text, answer_text, time_spent) VALUES (?, ?, ?, ?, ?)");
        $question_number = $index + 1;
        $time_spent = intval($answer['time_spent'] ?? 0);
        mysqli_stmt_bind_param($stmt, "sissi", $application_id, $question_number, $answer['question'], $answer['answer'], $time_spent);
        mysqli_stmt_execute($stmt);
    }
    
    // Actualizar aplicación como completada con skills
    $stmt = mysqli_prepare($conn, "UPDATE job_applications SET status = 'completed', score = ?, detected_skills = ?, completed_at = NOW() WHERE id = ?");
    $skillsJson = json_encode($detectedSkills);
    mysqli_stmt_bind_param($stmt, "dss", $final_score, $skillsJson, $application_id);
    mysqli_stmt_execute($stmt);
    
    // Auto-agregar al kanban
    addToKanban($application_id, $conn);
    
    echo json_encode(['success' => true, 'message' => 'Postulación enviada correctamente', 'score' => $final_score]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

mysqli_close($conn);

// Función para analizar skills
function analyzeTextForSkills($texto) {
    global $conn;
    
    $texto = strtolower($texto);
    $skillsDetected = [];
    
    try {
        $stmt = mysqli_prepare($conn, "SELECT * FROM job_keywords ORDER BY category");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $words = json_decode($row['words'], true) ?: [];
            $label = $row['label'];
            $category = $row['category'];
            $count = 0;
            
            foreach ($words as $palabra) {
                if (strpos($texto, strtolower($palabra)) !== false) {
                    $count++;
                }
            }
            
            if ($count > 0) {
                $skillsDetected[$category] = [
                    'count' => $count,
                    'label' => $label
                ];
            }
        }
        
    } catch (Exception $e) {
        // Si falla, devolver array vacío
    }
    
    return $skillsDetected;
}

// Función para agregar automáticamente al kanban
function addToKanban($application_id, $conn) {
    try {
        // Obtener datos de la aplicación
        $stmt = mysqli_prepare($conn, "SELECT user_id, position FROM job_applications WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "s", $application_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $app = mysqli_fetch_assoc($result);
        
        if (!$app) return;
        
        // Verificar si ya existe en kanban
        $stmt = mysqli_prepare($conn, "SELECT id FROM kanban_cards WHERE user_id = ? AND position = ?");
        mysqli_stmt_bind_param($stmt, "ss", $app['user_id'], $app['position']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_fetch_assoc($result)) return; // Ya existe
        
        // Obtener ID de columna "Nuevos"
        $stmt = mysqli_prepare($conn, "SELECT id FROM kanban_columns WHERE name = 'Nuevos' LIMIT 1");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $column = mysqli_fetch_assoc($result);
        
        if (!$column) return;
        
        // Agregar a kanban_cards
        $stmt = mysqli_prepare($conn, "INSERT INTO kanban_cards (user_id, position, column_id, card_position, created_at) VALUES (?, ?, ?, 0, NOW())");
        mysqli_stmt_bind_param($stmt, "ssi", $app['user_id'], $app['position'], $column['id']);
        mysqli_stmt_execute($stmt);
        
    } catch (Exception $e) {
        // Si falla, no interrumpir el flujo principal
    }
}
?>