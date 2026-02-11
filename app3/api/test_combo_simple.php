<?php
header('Content-Type: application/json');

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
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['error' => 'Config not found']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Crear combo simple
    $stmt = $pdo->prepare("
        INSERT INTO combos (name, description, price, category_id, active)
        VALUES (?, ?, ?, 8, 1)
    ");
    $stmt->execute(['Combo Test', 'Combo de prueba', 5000]);
    $combo_id = $pdo->lastInsertId();
    
    // Usar productos que existan - obtener los primeros 2 productos
    $stmt_products = $pdo->query("SELECT id FROM products LIMIT 2");
    $products = $stmt_products->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($products) >= 2) {
        // Agregar productos fijos
        $stmt = $pdo->prepare("
            INSERT INTO combo_items (combo_id, product_id, quantity, is_selectable)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$combo_id, $products[0], 1]);
        $stmt->execute([$combo_id, $products[1], 1]);
        
        // Agregar grupo de bebidas en combo_selections
        $stmt = $pdo->prepare("
            INSERT INTO combo_selections (combo_id, selection_group, product_id, additional_price, max_selections)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        // Usar el primer producto como bebida
        $stmt->execute([$combo_id, 'Bebidas', $products[0], 0, 1]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'combo_id' => $combo_id,
        'message' => 'Combo test creado correctamente'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>