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
        CREATE TABLE IF NOT EXISTS participant_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            participant_id VARCHAR(50) NOT NULL,
            viewer_id VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_like (participant_id, viewer_id)
        )
    ");

    $input = json_decode(file_get_contents('php://input'), true);
    $participantId = $input['participant_id'] ?? '';
    $viewerId = $input['viewer_id'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!$participantId || !$viewerId) {
        echo json_encode(['error' => 'Faltan datos requeridos']);
        exit;
    }

    // Verificar si ya dio like
    $stmt = $pdo->prepare("SELECT id FROM participant_likes WHERE participant_id = ? AND viewer_id = ?");
    $stmt->execute([$participantId, $viewerId]);
    
    if ($stmt->fetch()) {
        // Ya dio like, remover (toggle)
        $stmt = $pdo->prepare("DELETE FROM participant_likes WHERE participant_id = ? AND viewer_id = ?");
        $stmt->execute([$participantId, $viewerId]);
        $action = 'removed';
    } else {
        // Dar like
        $stmt = $pdo->prepare("INSERT INTO participant_likes (participant_id, viewer_id, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$participantId, $viewerId, $ipAddress]);
        $action = 'added';
    }

    // Contar likes totales del participante
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM participant_likes WHERE participant_id = ?");
    $stmt->execute([$participantId]);
    $likes = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'action' => $action,
        'likes' => (int)$likes
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>