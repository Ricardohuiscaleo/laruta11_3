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

try {
    // Obtener promedio de calidad de los últimos 30 días
    $stmt = $pdo->prepare("
        SELECT 
            AVG(score_percentage) as average_score,
            COUNT(*) as total_checklists,
            COUNT(DISTINCT role) as roles_count,
            COUNT(DISTINCT checklist_date) as days_count
        FROM quality_checklists 
        WHERE checklist_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'average_score' => $result['average_score'] ? round($result['average_score'], 1) : 0,
        'total_checklists' => $result['total_checklists'],
        'roles_count' => $result['roles_count'],
        'days_count' => $result['days_count']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>