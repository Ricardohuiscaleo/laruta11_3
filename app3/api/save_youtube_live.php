<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'youtube_helper.php';

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
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    // Crear tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS youtube_live (
        id INT PRIMARY KEY DEFAULT 1,
        original_url TEXT,
        embed_url VARCHAR(255),
        active TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $original_url = $input['url'] ?? '';
        
        // Limpiar URL usando la función helper
        $embed_url = get_clean_youtube_embed_url($original_url);
        
        if (empty($embed_url)) {
            echo json_encode(['success' => false, 'error' => 'URL de YouTube inválida']);
            exit;
        }
        
        // Insertar o actualizar
        $stmt = $pdo->prepare("INSERT INTO youtube_live (id, original_url, embed_url, active) 
                              VALUES (1, ?, ?, 1) 
                              ON DUPLICATE KEY UPDATE 
                              original_url = VALUES(original_url), 
                              embed_url = VALUES(embed_url),
                              active = VALUES(active),
                              updated_at = CURRENT_TIMESTAMP");
        
        $stmt->execute([$original_url, $embed_url]);
        
        echo json_encode([
            'success' => true, 
            'embed_url' => $embed_url,
            'message' => 'Video de YouTube guardado correctamente'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>