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
    die('Error de conexión a BD');
}

mysqli_set_charset($conn, 'utf8');

if (!isset($_GET['candidate_id'])) {
    die('ID de candidato requerido');
}

$candidateId = $_GET['candidate_id'];

try {
    // Obtener datos del candidato
    $candidateQuery = "SELECT * FROM job_applications WHERE user_id = ? OR id = ? ORDER BY updated_at DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $candidateQuery);
    mysqli_stmt_bind_param($stmt, "ss", $candidateId, $candidateId);
    mysqli_stmt_execute($stmt);
    $candidateResult = mysqli_stmt_get_result($stmt);
    $candidate = mysqli_fetch_assoc($candidateResult);
    
    if (!$candidate) {
        die('Candidato no encontrado');
    }
    
    // Obtener datos de la entrevista
    $interviewQuery = "SELECT * FROM interviews WHERE candidate_id = ? ORDER BY updated_at DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $interviewQuery);
    mysqli_stmt_bind_param($stmt, "s", $candidateId);
    mysqli_stmt_execute($stmt);
    $interviewResult = mysqli_stmt_get_result($stmt);
    $interview = mysqli_fetch_assoc($interviewResult);
    
    // Generar PDF simple usando FPDF o similar
    generateSimplePDF($candidate, $interview);
    
} catch (Exception $e) {
    die('Error generando PDF: ' . $e->getMessage());
}

function generateSimplePDF($candidate, $interview) {
    // Headers para descarga
    $filename = 'entrevista_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $candidate['nombre']) . '_' . date('Y-m-d') . '.txt';
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generar contenido de texto plano
    echo "=====================================\n";
    echo "       ENTREVISTA TELEFÓNICA\n";
    echo "           La Ruta 11\n";
    echo "=====================================\n\n";
    
    echo "FECHA: " . ($interview ? date('d/m/Y H:i', strtotime($interview['interview_date'])) : date('d/m/Y H:i')) . "\n\n";
    
    echo "DATOS DEL CANDIDATO:\n";
    echo "--------------------\n";
    echo "Nombre: " . $candidate['nombre'] . "\n";
    echo "Email: " . $candidate['email'] . "\n";
    echo "Teléfono: " . $candidate['telefono'] . "\n";
    echo "Posición: " . ($candidate['position'] === 'maestro_sanguchero' ? 'Maestro Sanguchero' : 'Cajero') . "\n";
    echo "Estado: " . ($interview ? ucfirst($interview['status']) : 'Sin entrevista') . "\n\n";
    
    if ($interview) {
        $yesNoAnswers = json_decode($interview['yes_no_answers'], true) ?: [];
        $openAnswers = json_decode($interview['open_answers'], true) ?: [];
        
        // Preguntas desde base de datos
        $questions = getQuestionsFromDatabase($candidate['position'] ?? 'cajero', $conn);
        
        echo "PREGUNTAS DE CONFIRMACIÓN (SÍ/NO):\n";
        echo "-----------------------------------\n";
        foreach ($questions['yesNo'] as $index => $question) {
            $answer = $yesNoAnswers[$index] ?? 'Sin respuesta';
            echo ($index + 1) . ". " . $question . "\n";
            echo "   Respuesta: " . strtoupper($answer) . "\n\n";
        }
        
        echo "PREGUNTAS ABIERTAS:\n";
        echo "-------------------\n";
        foreach ($questions['open'] as $index => $question) {
            $answer = $openAnswers[$index] ?? 'Sin respuesta';
            echo ($index + 1) . ". " . $question . "\n";
            echo "   Respuesta: " . $answer . "\n\n";
        }
        
        if ($interview['notes']) {
            echo "NOTAS DE LA ENTREVISTA:\n";
            echo "-----------------------\n";
            echo $interview['notes'] . "\n\n";
        }
    } else {
        echo "No se ha realizado entrevista para este candidato.\n\n";
    }
    
    echo "=====================================\n";
    echo "Documento generado automáticamente\n";
    echo "Sistema La Ruta 11\n";
    echo "Fecha: " . date('d/m/Y H:i:s') . "\n";
    echo "=====================================\n";
}

function getQuestionsFromDatabase($position, $conn) {
    $questions = ['yesNo' => [], 'open' => []];
    
    try {
        $query = "SELECT question_type, question_text FROM interview_questions WHERE position = ? AND is_active = TRUE ORDER BY question_type, question_order";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $position);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['question_type'] === 'yesno') {
                $questions['yesNo'][] = $row['question_text'];
            } else {
                $questions['open'][] = $row['question_text'];
            }
        }
        
        return $questions;
    } catch (Exception $e) {
        return ['yesNo' => [], 'open' => []];
    }
}

mysqli_close($conn);
?>