<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM concurso_registros WHERE id = ?");
    $stmt->execute([$input['id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Concursante eliminado'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Concursante no encontrado']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
?>