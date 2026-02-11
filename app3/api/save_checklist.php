<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../config.php', 
    $_SERVER['DOCUMENT_ROOT'] . '/../../config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../../../config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/../../../../config.php'
];

$config_found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = include $path;
        $config_found = true;
        break;
    }
}

if (!$config_found || !isset($config['app_db_host'])) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['role']) || !isset($input['responses'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit;
}

$role = $input['role'];
$responses = $input['responses'];
$checklist_date = date('Y-m-d');

// Validar role
if (!in_array($role, ['planchero', 'cajero'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit;
}

try {
    // Calcular estadísticas
    $total_questions = count($responses);
    $passed_questions = 0;
    
    foreach ($responses as $response) {
        if (isset($response['answer']) && $response['answer'] === 'si') {
            $passed_questions++;
        }
    }
    
    $score_percentage = $total_questions > 0 ? ($passed_questions / $total_questions) * 100 : 0;
    
    // Insertar o actualizar checklist (un checklist por día por rol)
    $stmt = $pdo->prepare("
        INSERT INTO quality_checklists 
        (role, checklist_date, responses, total_questions, passed_questions, score_percentage) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        responses = VALUES(responses),
        total_questions = VALUES(total_questions),
        passed_questions = VALUES(passed_questions),
        score_percentage = VALUES(score_percentage),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        $role,
        $checklist_date,
        json_encode($responses),
        $total_questions,
        $passed_questions,
        round($score_percentage, 2)
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Checklist guardado correctamente',
        'data' => [
            'role' => $role,
            'date' => $checklist_date,
            'total_questions' => $total_questions,
            'passed_questions' => $passed_questions,
            'score_percentage' => round($score_percentage, 2)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>