<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener categorías activas ordenadas
    $stmt = $pdo->prepare("
        SELECT id, slug as category_key, display_name, icon_type, color, sort_order, is_active, filter_config
        FROM menu_categories
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parsear filter_config JSON
    foreach ($categories as &$cat) {
        if ($cat['filter_config']) {
            $cat['filter_config'] = json_decode($cat['filter_config'], true);
        }
    }
    
    // Para cada categoría, obtener sus subcategorías
    foreach ($categories as &$category) {
        $stmt = $pdo->prepare("
            SELECT id, display_name, sort_order, is_active
            FROM menu_subcategories
            WHERE menu_category_id = :menu_category_id
            ORDER BY sort_order ASC
        ");
        $stmt->execute(['menu_category_id' => $category['id']]);
        $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $category['subcategories'] = $subcategories;
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
