<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../'];
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
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error de conexión', 'active_viewers' => 0]);
        exit;
    }
} else {
    echo json_encode(['error' => 'Config no encontrado', 'active_viewers' => 0]);
    exit;
}

try {
    // Limpiar viewers inactivos
    $pdo->exec("DELETE FROM live_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
    
    // Contar viewers activos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM live_viewers");
    $count = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'active_viewers' => (int)$count
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'active_viewers' => 0
    ]);
}
?>