<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

$role = $_GET['role'] ?? '';

if (empty($role)) {
    echo json_encode(['success' => false, 'error' => 'Role is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM quality_questions WHERE role = ? AND active = 1 ORDER BY order_index");
    $stmt->execute([$role]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'questions' => $questions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>