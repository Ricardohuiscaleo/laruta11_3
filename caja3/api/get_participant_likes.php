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
        echo json_encode(['error' => 'Error de conexión', 'likes' => []]);
        exit;
    }
} else {
    echo json_encode(['error' => 'Config no encontrado', 'likes' => []]);
    exit;
}

try {
    $viewerId = $_GET['viewer_id'] ?? '';
    
    // Obtener likes por participante
    $stmt = $pdo->query("
        SELECT participant_id, COUNT(*) as likes 
        FROM participant_likes 
        GROUP BY participant_id
    ");
    $likesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $likes = [];
    foreach ($likesData as $row) {
        $likes[$row['participant_id']] = (int)$row['likes'];
    }
    
    // Si se proporciona viewer_id, obtener sus likes
    $userLikes = [];
    if ($viewerId) {
        $stmt = $pdo->prepare("SELECT participant_id FROM participant_likes WHERE viewer_id = ?");
        $stmt->execute([$viewerId]);
        $userLikesData = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $userLikes = array_flip($userLikesData);
    }

    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'user_likes' => $userLikes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'likes' => []
    ]);
}
?>