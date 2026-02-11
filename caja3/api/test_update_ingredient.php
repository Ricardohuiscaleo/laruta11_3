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
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB Connection: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Config file not found']);
    exit;
}

try {
    // Mostrar datos recibidos
    $postData = $_POST;
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['error' => 'No ID provided', 'received_data' => $postData]);
        exit;
    }
    
    // Verificar que el ingrediente existe
    $stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = ?");
    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ingredient) {
        echo json_encode(['error' => 'Ingredient not found', 'id' => $id]);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Test successful',
        'received_data' => $postData,
        'current_ingredient' => $ingredient
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>