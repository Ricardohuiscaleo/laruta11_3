<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Config file not found']);
    exit;
}

try {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            echo json_encode($producto);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Producto no encontrado']);
        }
    } else {
        $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] == '1';
        
        // Obtener productos normales
        if ($includeInactive) {
            $stmt = $pdo->prepare("SELECT *, 'product' as type FROM products ORDER BY is_active DESC, name ASC");
        } else {
            $stmt = $pdo->prepare("SELECT *, 'product' as type FROM products WHERE is_active = 1 ORDER BY name ASC");
        }
        $stmt->execute();
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener combos
        if ($includeInactive) {
            $stmt = $pdo->prepare("SELECT *, 'combo' as type, 8 as category_id FROM combos ORDER BY active DESC, name ASC");
        } else {
            $stmt = $pdo->prepare("SELECT *, 'combo' as type, 8 as category_id FROM combos WHERE active = 1 ORDER BY name ASC");
        }
        $stmt->execute();
        $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combinar productos y combos
        $productos = array_merge($productos, $combos);
        
        echo json_encode($productos);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>