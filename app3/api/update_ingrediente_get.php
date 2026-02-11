<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php hasta 5 niveles
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    throw new Exception('config.php no encontrado');
}

$config = require_once $configPath;

try {
    // Usar base de datos app
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Aceptar parámetros GET para testing
    $id = $_GET['id'] ?? null;
    $nombre = $_GET['name'] ?? $_GET['nombre'] ?? null;
    $stock = $_GET['stock'] ?? $_GET['current_stock'] ?? 0;

    if (!$id || !$nombre) {
        throw new Exception('Parámetros requeridos: id, name, stock. Ejemplo: ?id=1&name=Tomate&stock=10');
    }

    $stmt = $pdo->prepare("UPDATE ingredients SET name = ?, current_stock = ? WHERE id = ?");
    $stmt->execute([$nombre, $stock, $id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Ingrediente actualizado',
        'updated' => [
            'id' => $id,
            'name' => $nombre,
            'stock' => $stock
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>