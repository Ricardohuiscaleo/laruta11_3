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
    // Configurar zona horaria de Chile para futuros mensajes
    date_default_timezone_set('America/Santiago');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = trim($input['username'] ?? 'Anónimo');
        $message = trim($input['message'] ?? '');
        
        // Validaciones
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Mensaje vacío']);
            exit;
        }
        
        if (strlen($message) > 200) {
            echo json_encode(['success' => false, 'error' => 'Mensaje muy largo (máx 200 caracteres)']);
            exit;
        }
        
        if (strlen($username) > 50) {
            $username = substr($username, 0, 50);
        }
        
        // Filtro básico de palabras
        $badWords = ['spam', 'hack', 'bot'];
        $messageLower = strtolower($message);
        foreach ($badWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                echo json_encode(['success' => false, 'error' => 'Mensaje no permitido']);
                exit;
            }
        }
        
        // Insertar mensaje
        $stmt = $pdo->prepare("INSERT INTO chat_messages (username, message, approved) VALUES (?, ?, 1)");
        $stmt->execute([$username, $message]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Mensaje enviado correctamente',
            'id' => $pdo->lastInsertId()
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>