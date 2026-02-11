<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Manejar GET, POST FormData y POST JSON
// amazonq-ignore-next-line
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? null;
    $nombre = $_GET['name'] ?? $_GET['nombre'] ?? null;
    $stock = $_GET['stock'] ?? $_GET['current_stock'] ?? 0;
    $input = ['id' => $id, 'name' => $nombre, 'stock' => $stock];
} else {
    // POST: Intentar FormData primero, luego JSON
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        // amazonq-ignore-next-line
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }
}

// Buscar config.php hasta 5 niveles
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    // amazonq-ignore-next-line
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    throw new Exception('config.php no encontrado');
}

// amazonq-ignore-next-line
$config = require_once $configPath;

try {
    // Usar base de datos app
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Debug: log de datos recibidos
    // amazonq-ignore-next-line
    error_log('Update ingrediente - Input: ' . json_encode($input));
    
    $id = $input['id'] ?? null;
    $nombre = $input['nombre'] ?? $input['name'] ?? null;
    $stock = $input['stock'] ?? $input['current_stock'] ?? 0;
    
    // Mapear campos del frontend
    // amazonq-ignore-next-line
    if (!$nombre && isset($input['name'])) $nombre = $input['name'];
    if (!$stock && isset($input['current_stock'])) $stock = $input['current_stock'];

    if (!$id || !$nombre) {
        throw new Exception('ID y nombre requeridos. Recibido: id=' . $id . ', nombre=' . $nombre);
    }

    // amazonq-ignore-next-line
    $stmt = $pdo->prepare("UPDATE ingredients SET name = ?, current_stock = ? WHERE id = ?");
    $stmt->execute([$nombre, $stock, $id]);

    echo json_encode(['success' => true, 'message' => 'Ingrediente actualizado']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>