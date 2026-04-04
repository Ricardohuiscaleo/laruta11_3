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
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

try {
    // Check if specific combo_id is requested
    $combo_id = isset($_GET['combo_id']) ? (int)$_GET['combo_id'] : null;
    $combo_name = isset($_GET['name']) ? trim($_GET['name']) : null;
    
    if ($combo_id) {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.name as category_name 
            FROM combos c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            WHERE c.active = 1 AND c.id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$combo_id]);
    } elseif ($combo_name) {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.name as category_name 
            FROM combos c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            WHERE c.active = 1 AND c.name = ?
            ORDER BY c.name
        ");
        $stmt->execute([$combo_name]);
    } else {
        $stmt = $pdo->query("
            SELECT c.*, cat.name as category_name 
            FROM combos c 
            LEFT JOIN categories cat ON c.category_id = cat.id 
            WHERE c.active = 1 
            ORDER BY c.name
        ");
    }
    
    $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada combo, obtener sus items y selecciones
    foreach ($combos as &$combo) {
        // Items fijos del combo
        $stmt_items = $pdo->prepare("
            SELECT ci.*, COALESCE(p.name, 'Producto no encontrado') as product_name, COALESCE(p.price, 0) as product_price, p.image_url
            FROM combo_items ci
            LEFT JOIN products p ON ci.product_id = p.id
            WHERE ci.combo_id = ? AND ci.is_selectable = 0
        ");
        $stmt_items->execute([$combo['id']]);
        $combo['fixed_items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        // Grupos de selección
        $stmt_groups = $pdo->prepare("
            SELECT DISTINCT selection_group 
            FROM combo_selections 
            WHERE combo_id = ?
        ");
        $stmt_groups->execute([$combo['id']]);
        $groups = $stmt_groups->fetchAll(PDO::FETCH_COLUMN);
        
        $combo['selection_groups'] = [];
        foreach ($groups as $group) {
            $stmt_options = $pdo->prepare("
                SELECT cs.*, 
                       COALESCE(p.name, 'Producto no encontrado') as product_name, 
                       COALESCE(p.price, 0) as product_price,
                       p.image_url
                FROM combo_selections cs
                LEFT JOIN products p ON cs.product_id = p.id
                WHERE cs.combo_id = ? AND cs.selection_group = ?
            ");
            $stmt_options->execute([$combo['id'], $group]);
            $combo['selection_groups'][$group] = $stmt_options->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Debug: Log what we found
    error_log('Combo query result: ' . print_r($combos, true));
    
    echo json_encode(['success' => true, 'combos' => $combos, 'debug' => [
        'combo_id_requested' => $combo_id,
        'combos_found' => count($combos)
    ]]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>