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
if (!$configPath) {
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

$config = include $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }

    // Crear tabla si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS concurso_state (
            id INT PRIMARY KEY DEFAULT 1,
            tournament_data JSON NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Insertar o actualizar estado
    $stmt = $pdo->prepare("
        INSERT INTO concurso_state (id, tournament_data) 
        VALUES (1, ?) 
        ON DUPLICATE KEY UPDATE tournament_data = ?, updated_at = CURRENT_TIMESTAMP
    ");
    
    $jsonData = json_encode($input);
    $stmt->execute([$jsonData, $jsonData]);

    echo json_encode(['success' => true, 'message' => 'Estado actualizado']);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>