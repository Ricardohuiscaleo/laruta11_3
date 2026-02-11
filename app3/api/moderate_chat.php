<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $messageId = intval($input['message_id'] ?? 0);
        $action = $input['action'] ?? ''; // 'approve', 'reject', 'delete'
        
        if ($messageId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de mensaje inválido']);
            exit;
        }
        
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE chat_messages SET approved = 1 WHERE id = ?");
                $stmt->execute([$messageId]);
                $message = 'Mensaje aprobado';
                break;
                
            case 'reject':
                $stmt = $pdo->prepare("UPDATE chat_messages SET approved = 0 WHERE id = ?");
                $stmt->execute([$messageId]);
                $message = 'Mensaje rechazado';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = ?");
                $stmt->execute([$messageId]);
                $message = 'Mensaje eliminado';
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción inválida']);
                exit;
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'action' => $action,
            'message_id' => $messageId
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>