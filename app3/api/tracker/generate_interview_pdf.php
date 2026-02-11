<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

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
    
    // Preguntas por posición
    $questionsByPosition = [
        'cajero' => [
            'yesNo' => [
                "¿Acepta trabajar con aplicación móvil de KPIs que mide su rendimiento diario?",
                "¿Está de acuerdo con sistema de bonificaciones basado en ventas y atención al cliente?",
                "¿Acepta que los clientes califiquen el servicio en Google Maps y redes sociales?",
                "¿Está dispuesto/a a cumplir metas de ventas diarias específicas?",
                "¿Acepta promocionar y vender productos especiales del día?",
                "¿Está de acuerdo con el rango salarial discutido (incluye base + bonos)?",
                "¿Acepta trabajar fines de semana, feriados y horarios peak?",
                "¿Tiene disponibilidad para turnos rotativos (mañana, tarde, noche)?",
                "¿Acepta capacitación continua en nuevos productos y técnicas de venta?",
                "¿Está cómodo/a manejando dinero en efectivo y sistemas de pago digital?",
                "¿Acepta todos los términos y condiciones laborales explicados?"
            ],
            'open' => [
                "¿Qué te motiva a trabajar en el área de atención al cliente en La Ruta 11?",
                "Describe cómo resolverías un conflicto con un cliente insatisfecho",
                "¿Cuál es tu experiencia previa en ventas, caja o atención al público?"
            ]
        ],
        'maestro_sanguchero' => [
            'yesNo' => [
                "¿Acepta trabajar con aplicación móvil que registra tiempos y calidad de preparación?",
                "¿Está de acuerdo con bonificaciones por productividad y calidad del producto?",
                "¿Acepta que los clientes califiquen la comida en Google Maps y delivery apps?",
                "¿Está dispuesto/a a cumplir metas de producción y tiempos de entrega?",
                "¿Acepta preparar productos especiales y nuevas recetas del menú?",
                "¿Está de acuerdo con el rango salarial discutido (incluye base + bonos)?",
                "¿Acepta trabajar en horarios de alta demanda y rush de pedidos?",
                "¿Tiene disponibilidad para turnos de cocina en diferentes horarios?",
                "¿Acepta capacitación constante en nuevas técnicas culinarias?",
                "¿Está dispuesto/a a mantener estándares estrictos de higiene y calidad?",
                "¿Acepta todos los términos y condiciones laborales explicados?"
            ],
            'open' => [
                "¿Qué experiencia tienes en cocina rápida o preparación de sandwiches/completos?",
                "¿Cómo organizas tu trabajo cuando hay muchos pedidos simultáneos?",
                "¿Por qué te interesa específicamente el puesto de maestro sanguchero?"
            ]
        ]
    ];
    
    // Generar HTML
    $html = generateInterviewHTML($candidate, $interview, $questionsByPosition);
    
    // Usar wkhtmltopdf o biblioteca similar para generar PDF real
    $filename = 'entrevista_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $candidate['nombre']) . '_' . date('Y-m-d') . '.pdf';
    
    // Método 1: Usar wkhtmltopdf si está disponible
    if (command_exists('wkhtmltopdf')) {
        $tempHtml = tempnam(sys_get_temp_dir(), 'interview_') . '.html';
        file_put_contents($tempHtml, $html);
        
        $tempPdf = tempnam(sys_get_temp_dir(), 'interview_') . '.pdf';
        $command = "wkhtmltopdf --page-size A4 --margin-top 20mm --margin-bottom 20mm '$tempHtml' '$tempPdf'";
        exec($command);
        
        if (file_exists($tempPdf)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            readfile($tempPdf);
            unlink($tempHtml);
            unlink($tempPdf);
        } else {
            // Fallback a HTML
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
        }
    } else {
        // Método 2: Usar navegador para imprimir (HTML con CSS de impresión)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo addPrintStyles($html);
    }
    
} catch (Exception $e) {
    die('Error generando PDF: ' . $e->getMessage());
}

function generateInterviewHTML($candidate, $interview, $questionsByPosition) {
    $position = $candidate['position'] ?? 'cajero';
    $positionName = $position === 'maestro_sanguchero' ? 'Maestro Sanguchero' : 'Cajero';
    
    $yesNoAnswers = $interview ? json_decode($interview['yes_no_answers'], true) : [];
    $openAnswers = $interview ? json_decode($interview['open_answers'], true) : [];
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Entrevista - ' . htmlspecialchars($candidate['nombre']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .section { margin-bottom: 25px; }
            .question { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; }
            .answer { font-weight: bold; color: #2563eb; }
            .notes { background: #f9f9f9; padding: 15px; border-radius: 5px; }
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
            <p><strong>Nombre:</strong> ' . htmlspecialchars($candidate['nombre']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($candidate['email']) . '</p>
            <p><strong>Teléfono:</strong> ' . htmlspecialchars($candidate['telefono']) . '</p>
            <p><strong>Posición:</strong> ' . $positionName . '</p>
            <p><strong>Estado:</strong> ' . ($interview ? ucfirst($interview['status']) : 'Sin entrevista') . '</p>
        </div>';
    
    if ($interview) {
        $questions = $questionsByPosition[$position];
        
        $html .= '
        <div class="section">
            <h3>PREGUNTAS DE CONFIRMACIÓN (SÍ/NO)</h3>';
        
        foreach ($questions['yesNo'] as $index => $question) {
            $answer = $yesNoAnswers[$index] ?? 'Sin respuesta';
            $html .= '
            <div class="question">
                <p><strong>' . ($index + 1) . '.</strong> ' . htmlspecialchars($question) . '</p>
                <p class="answer">Respuesta: ' . strtoupper($answer) . '</p>
            </div>';
        }
        
        $html .= '
        </div>
        
        <div class="section">
            <h3>PREGUNTAS ABIERTAS</h3>';
        
        foreach ($questions['open'] as $index => $question) {
            $answer = $openAnswers[$index] ?? 'Sin respuesta';
            $html .= '
            <div class="question">
                <p><strong>' . ($index + 1) . '.</strong> ' . htmlspecialchars($question) . '</p>
                <p class="answer">' . htmlspecialchars($answer) . '</p>
            </div>';
        }
        
        $html .= '</div>';
        
        if ($interview['notes']) {
            $html .= '
            <div class="section">
                <h3>NOTAS DE LA ENTREVISTA</h3>
                <div class="notes">
                    ' . nl2br(htmlspecialchars($interview['notes'])) . '
                </div>
            </div>';
        }
    } else {
        $html .= '
        <div class="section">
            <p><em>No se ha realizado entrevista para este candidato.</em></p>
        </div>';
    }
    
    $html .= '
        <div class="section" style="margin-top: 40px; text-align: center; font-size: 12px; color: #666;">
            <p>Documento generado automáticamente por el sistema de La Ruta 11</p>
            <p>Fecha de generación: ' . date('d/m/Y H:i:s') . '</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

function command_exists($cmd) {
    $return = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
    return !empty($return);
}

function addPrintStyles($html) {
    $printStyles = '
    <style media="print">
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