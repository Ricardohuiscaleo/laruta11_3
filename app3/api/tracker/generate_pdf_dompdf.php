<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

// Intentar cargar Dompdf (si está instalado via Composer)
$dompdfPath = __DIR__ . '/../../vendor/autoload.php';
$hasDompdf = false;
if (file_exists($dompdfPath)) {
    require_once $dompdfPath;
    if (class_exists('Dompdf\\Dompdf')) {
        $hasDompdf = true;
    }
}

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
    
    // Generar HTML
    $html = generatePDFHTML($candidate, $interview);
    
    if ($hasDompdf) {
        // Usar Dompdf para generar PDF real
        generateWithDompdf($html, $candidate['nombre']);
    } else {
        // Fallback: usar API externa o HTML optimizado
        generateWithExternalAPI($html, $candidate['nombre']);
    }
    
} catch (Exception $e) {
    die('Error generando PDF: ' . $e->getMessage());
}

function generateWithDompdf($html, $candidateName) {
    if (!class_exists('\Dompdf\Dompdf')) {
        generateWithExternalAPI($html, $candidateName);
        return;
    }
    
    try {
        $optionsClass = '\Dompdf\Options';
        $dompdfClass = '\Dompdf\Dompdf';
        
        $options = new $optionsClass();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new $dompdfClass($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'entrevista_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $candidateName) . '_' . date('Y-m-d') . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $dompdf->output();
    } catch (Exception $e) {
        generateWithExternalAPI($html, $candidateName);
    }
}

function generateWithExternalAPI($html, $candidateName) {
    // Usar API externa como html-pdf-api.com o similar
    $apiUrl = 'https://api.html-css-to-pdf.com/v1/generate';
    
    $postData = json_encode([
        'html' => $html,
        'css' => '',
        'options' => [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margin' => '20mm'
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $filename = 'entrevista_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $candidateName) . '_' . date('Y-m-d') . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $response;
    } else {
        // Fallback final: HTML optimizado para impresión
        header('Content-Type: text/html; charset=utf-8');
        echo addPrintStyles($html);
    }
}

function generatePDFHTML($candidate, $interview) {
    $position = $candidate['position'] ?? 'cajero';
    $positionName = $position === 'maestro_sanguchero' ? 'Maestro Sanguchero' : 'Cajero';
    
    $yesNoAnswers = $interview ? json_decode($interview['yes_no_answers'], true) : [];
    $openAnswers = $interview ? json_decode($interview['open_answers'], true) : [];
    
    $questions = getQuestionsFromDatabase($position);
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Entrevista - ' . htmlspecialchars($candidate['nombre']) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            font-size: 12px;
            line-height: 1.4;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 { 
            margin: 0; 
            color: #333; 
            font-size: 24px;
        }
        .header h2 { 
            margin: 5px 0; 
            color: #666; 
            font-size: 18px;
        }
        .section { 
            margin-bottom: 25px; 
            page-break-inside: avoid;
        }
        .section h3 { 
            background: #f0f0f0; 
            padding: 8px; 
            margin: 0 0 15px 0; 
            border-left: 4px solid #333;
            font-size: 14px;
        }
        .question { 
            margin-bottom: 15px; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            background: #fafafa;
        }
        .question p { 
            margin: 0 0 5px 0; 
        }
        .answer { 
            font-weight: bold; 
            color: #2563eb; 
            background: white;
            padding: 5px;
            border-radius: 3px;
        }
        .notes { 
            background: #f9f9f9; 
            padding: 15px; 
            border-radius: 5px; 
            border: 1px solid #ddd;
        }
        .candidate-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .candidate-info div {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ENTREVISTA TELEFÓNICA</h1>
        <h2>La Ruta 11</h2>
        <p><strong>Fecha:</strong> ' . ($interview ? date('d/m/Y H:i', strtotime($interview['interview_date'])) : date('d/m/Y H:i')) . '</p>
    </div>
    
    <div class="section">
        <h3>DATOS DEL CANDIDATO</h3>
        <div class="candidate-info">
            <div><strong>Nombre:</strong> ' . htmlspecialchars($candidate['nombre']) . '</div>
            <div><strong>Email:</strong> ' . htmlspecialchars($candidate['email']) . '</div>
            <div><strong>Teléfono:</strong> ' . htmlspecialchars($candidate['telefono']) . '</div>
            <div><strong>Posición:</strong> ' . $positionName . '</div>
            <div><strong>Estado:</strong> ' . ($interview ? ucfirst($interview['status']) : 'Sin entrevista') . '</div>
            <div><strong>Score:</strong> ' . ($candidate['best_score'] ?? 'N/A') . '%</div>
        </div>
    </div>';
    
    if ($interview) {
        $html .= '<div class="section">
            <h3>PREGUNTAS DE CONFIRMACIÓN (SÍ/NO)</h3>';
        
        foreach ($questions['yesNo'] as $index => $question) {
            $answer = $yesNoAnswers[$index] ?? 'Sin respuesta';
            $html .= '<div class="question">
                <p><strong>' . ($index + 1) . '.</strong> ' . htmlspecialchars($question) . '</p>
                <div class="answer">Respuesta: ' . strtoupper($answer) . '</div>
            </div>';
        }
        
        $html .= '</div><div class="section">
            <h3>PREGUNTAS ABIERTAS</h3>';
        
        foreach ($questions['open'] as $index => $question) {
            $answer = $openAnswers[$index] ?? 'Sin respuesta';
            $html .= '<div class="question">
                <p><strong>' . ($index + 1) . '.</strong> ' . htmlspecialchars($question) . '</p>
                <div class="answer">' . nl2br(htmlspecialchars($answer)) . '</div>
            </div>';
        }
        
        $html .= '</div>';
        
        if ($interview['notes']) {
            $html .= '<div class="section">
                <h3>NOTAS DE LA ENTREVISTA</h3>
                <div class="notes">' . nl2br(htmlspecialchars($interview['notes'])) . '</div>
            </div>';
        }
    } else {
        $html .= '<div class="section">
            <p><em>No se ha realizado entrevista para este candidato.</em></p>
        </div>';
    }
    
    $html .= '<div class="footer">
        <p>Documento generado automáticamente por el sistema de La Ruta 11</p>
        <p>Fecha de generación: ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';
    
    return $html;
}

function getQuestionsFromDatabase($position) {
    global $conn;
    
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
        // Fallback vacío si hay error
        return ['yesNo' => [], 'open' => []];
    }
}

function addPrintStyles($html) {
    $printStyles = '<style media="print">
        @page { margin: 2cm; }
        body { font-size: 12pt; }
        .no-print { display: none; }
    </style>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>';
    
    return str_replace('</head>', $printStyles . '</head>', $html);
}

mysqli_close($conn);
?>