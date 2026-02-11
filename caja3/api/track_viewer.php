<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
        echo json_encode(['error' => 'Error de conexión']);
        exit;
    }
} else {
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

try {
    // Crear tabla si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS live_viewers (
            id VARCHAR(50) PRIMARY KEY,
            ip_address VARCHAR(45),
            user_agent TEXT,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $input = json_decode(file_get_contents('php://input'), true);
    $viewerId = $input['viewer_id'] ?? uniqid();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Insertar o actualizar viewer
    $stmt = $pdo->prepare("
        INSERT INTO live_viewers (id, ip_address, user_agent, last_seen) 
        VALUES (?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE last_seen = NOW()
    ");
    $stmt->execute([$viewerId, $ipAddress, $userAgent]);

    // Limpiar viewers inactivos (más de 30 segundos)
    $pdo->exec("DELETE FROM live_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");

    // Contar viewers activos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM live_viewers");
    $count = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'viewer_id' => $viewerId,
        'active_viewers' => (int)$count
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>