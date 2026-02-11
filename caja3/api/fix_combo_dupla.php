<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    // Obtener ID del Combo Dupla
    $stmt = $pdo->prepare("SELECT id FROM combos WHERE name LIKE '%Dupla%' LIMIT 1");
    $stmt->execute();
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$combo) {
        echo json_encode(['success' => false, 'error' => 'Combo Dupla no encontrado']);
        exit;
    }
    
    $combo_id = $combo['id'];
    
    // Limpiar items existentes del combo
    $stmt = $pdo->prepare("DELETE FROM combo_items WHERE combo_id = ?");
    $stmt->execute([$combo_id]);
    
    // Buscar productos correctos para el Combo Dupla
    // Hamburguesa clásica
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name LIKE '%Hamburguesa%' AND name LIKE '%clásica%' LIMIT 1");
    $stmt->execute();
    $hamburguesa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ave italiana (sandwich)
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name LIKE '%Ave%' AND name LIKE '%italiana%' LIMIT 1");
    $stmt->execute();
    $ave = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Papas de la ruta 11
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name LIKE '%Papas%' AND name LIKE '%Ruta%' LIMIT 1");
    $stmt->execute();
    $papas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $items_added = 0;
    
    // Agregar hamburguesa si existe
    if ($hamburguesa) {
        $stmt = $pdo->prepare("INSERT INTO combo_items (combo_id, product_id, quantity, is_selectable) VALUES (?, ?, 1, 0)");
        $stmt->execute([$combo_id, $hamburguesa['id']]);
        $items_added++;
    }
    
    // Agregar ave italiana si existe
    if ($ave) {
        $stmt = $pdo->prepare("INSERT INTO combo_items (combo_id, product_id, quantity, is_selectable) VALUES (?, ?, 1, 0)");
        $stmt->execute([$combo_id, $ave['id']]);
        $items_added++;
    }
    
    // Agregar papas si existe
    if ($papas) {
        $stmt = $pdo->prepare("INSERT INTO combo_items (combo_id, product_id, quantity, is_selectable) VALUES (?, ?, 1, 0)");
        $stmt->execute([$combo_id, $papas['id']]);
        $items_added++;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Combo Dupla corregido",
        'combo_id' => $combo_id,
        'items_added' => $items_added,
        'found_products' => [
            'hamburguesa' => $hamburguesa ? $hamburguesa['id'] : null,
            'ave' => $ave ? $ave['id'] : null,
            'papas' => $papas ? $papas['id'] : null
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>