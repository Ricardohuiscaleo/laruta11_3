<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

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
    echo json_encode(['success' => false, 'error' => 'Conexión fallida']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        $position = $_GET['position'] ?? 'cajero';
        
        $stmt = mysqli_prepare($conn, "SELECT * FROM interview_questions WHERE position = ? AND is_active = 1 ORDER BY question_type, question_order, id");
        mysqli_stmt_bind_param($stmt, 's', $position);
        mysqli_stmt_execute($stmt);
        $result_set = mysqli_stmt_get_result($stmt);
        $questions = mysqli_fetch_all($result_set, MYSQLI_ASSOC);
        
        $result = ['yesno' => [], 'open' => []];
        foreach($questions as $q) {
            $result[$q['question_type']][] = $q;
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = mysqli_prepare($conn, "INSERT INTO interview_questions (position, question_type, question_text, question_order, is_active) VALUES (?, ?, ?, 0, 1)");
        mysqli_stmt_bind_param($stmt, 'sss', $data['position'], $data['question_type'], $data['question_text']);
        $result = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $result]);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = mysqli_prepare($conn, "UPDATE interview_questions SET question_text = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $data['question_text'], $data['id']);
        $result = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $result]);
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = mysqli_prepare($conn, "UPDATE interview_questions SET is_active = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $data['id']);
        $result = mysqli_stmt_execute($stmt);
        
        echo json_encode(['success' => $result]);
        break;
}
?>