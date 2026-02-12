<?php
header('Content-Type: application/json');

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
    die(json_encode(['success' => false, 'error' => 'Error de conexión a BD']));
}

mysqli_set_charset($conn, 'utf8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$application_id = $_POST['application_id'] ?? '';
$question_number = intval($_POST['question_number'] ?? 0);
$answer_text = $_POST['answer_text'] ?? '';

if (empty($application_id) || $question_number < 1 || $question_number > 3) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

try {
    // Actualizar la pregunta correspondiente
    $column = "pregunta" . $question_number;
    $stmt = mysqli_prepare($conn, "UPDATE job_applications SET $column = ?, status = 'in_progress' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ss", $answer_text, $application_id);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>