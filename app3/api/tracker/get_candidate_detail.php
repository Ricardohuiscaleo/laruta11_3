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

$candidateId = $_GET['id'] ?? '';
$position = $_GET['position'] ?? '';

error_log('Candidate ID received: ' . $candidateId);
error_log('Position received: ' . $position);

if (empty($candidateId)) {
    echo json_encode(['success' => false, 'error' => 'ID de candidato requerido']);
    exit();
}

try {
    // Primero obtener el registro específico para saber user_id y position
    if ($position) {
        // Si se proporciona position, usarlo directamente
        $specific_query = "
            SELECT user_id, position
            FROM job_applications
            WHERE (id = ? OR user_id = ?) AND position = ?
            LIMIT 1
        ";
        
        $stmt = mysqli_prepare($conn, $specific_query);
        mysqli_stmt_bind_param($stmt, "sss", $candidateId, $candidateId, $position);
    } else {
        // Si no se proporciona position, buscar cualquiera
        $specific_query = "
            SELECT user_id, position
            FROM job_applications
            WHERE id = ? OR user_id = ?
            LIMIT 1
        ";
        
        $stmt = mysqli_prepare($conn, $specific_query);
        mysqli_stmt_bind_param($stmt, "ss", $candidateId, $candidateId);
    }
    
    mysqli_stmt_execute($stmt);
    $specific_result = mysqli_stmt_get_result($stmt);
    $specific_data = mysqli_fetch_assoc($specific_result);
    
    if (!$specific_data) {
        echo json_encode(['success' => false, 'error' => 'Candidato no encontrado']);
        exit();
    }
    
    // Obtener datos completos del candidato para la posición específica
    $candidate_query = "
        SELECT 
            ja.user_id,
            ja.position,
            ja.nombre, 
            ja.telefono, 
            MAX(ja.score) as best_score,
            ja.curso_manipulador,
            ja.curso_cajero,
            ja.instagram,
            MAX(ja.completed_at) as completed_at,
            ja.nacionalidad,
            ja.genero,
            ja.requisitos_legales,
            COUNT(*) as total_attempts,
            MAX(ja.created_at) as last_attempt,
            MAX(ja.time_elapsed) as time_elapsed,
            u.foto_perfil,
            u.email
        FROM job_applications ja
        LEFT JOIN usuarios u ON ja.user_id = u.id
        WHERE ja.user_id = ? AND ja.position = ?
        GROUP BY ja.user_id, ja.position, ja.nombre, ja.telefono, ja.curso_manipulador, ja.curso_cajero, ja.instagram, ja.nacionalidad, ja.genero, ja.requisitos_legales
    ";
    
    $stmt = mysqli_prepare($conn, $candidate_query);
    mysqli_stmt_bind_param($stmt, "ss", $specific_data['user_id'], $specific_data['position']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $candidate = mysqli_fetch_assoc($result);
    
    // Obtener todos los intentos del candidato con datos completos
    $attempts_query = "
        SELECT 
            id,
            position,
            nombre,
            telefono,
            pregunta1,
            pregunta2,
            pregunta3,
            score,
            attempts,
            keyword_analysis,
            detected_skills,
            status,
            created_at,
            updated_at,
            curso_manipulador,
            curso_cajero,
            instagram,
            user_id,
            completed_at,
            nacionalidad,
            genero,
            requisitos_legales,
            time_elapsed
        FROM job_applications
        WHERE user_id = ? AND position = ?
        ORDER BY created_at DESC
    ";
    
    $stmt = mysqli_prepare($conn, $attempts_query);
    mysqli_stmt_bind_param($stmt, "ss", $candidate['user_id'], $candidate['position']);
    mysqli_stmt_execute($stmt);
    $attempts_result = mysqli_stmt_get_result($stmt);
    
    $attempts = [];
    while ($row = mysqli_fetch_assoc($attempts_result)) {
        // Convertir preguntas a formato de respuestas manteniendo datos originales
        $row['answers'] = [];
        if ($row['pregunta1']) {
            $row['answers'][] = ['question' => 'Pregunta 1', 'answer' => $row['pregunta1']];
        }
        if ($row['pregunta2']) {
            $row['answers'][] = ['question' => 'Pregunta 2', 'answer' => $row['pregunta2']];
        }
        if ($row['pregunta3']) {
            $row['answers'][] = ['question' => 'Pregunta 3', 'answer' => $row['pregunta3']];
        }
        
        // Decodificar keyword_analysis si existe
        if ($row['keyword_analysis']) {
            $row['keyword_analysis'] = json_decode($row['keyword_analysis'], true);
        }
        
        // Decodificar detected_skills si existe
        if ($row['detected_skills']) {
            $row['detected_skills'] = json_decode($row['detected_skills'], true);
        }
        
        // Decodificar requisitos_legales si existe
        if ($row['requisitos_legales']) {
            $row['requisitos_legales'] = json_decode($row['requisitos_legales'], true);
        }
        
        $attempts[] = $row;
    }
    
    $candidate['attempts'] = $attempts;
    
    echo json_encode([
        'success' => true,
        'data' => $candidate
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>