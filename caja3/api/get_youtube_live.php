<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php hasta 5 niveles
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if ($configPath) {
    $config = include $configPath;
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'embed_url' => '', 'error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'embed_url' => '', 'error' => 'Config no encontrado']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT embed_url FROM youtube_live WHERE id = 1 AND active = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'embed_url' => $result ? $result['embed_url'] : '',
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'embed_url' => '', 'error' => $e->getMessage()]);
}
?>