<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
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

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();
    
    if (isset($data['id']) && $data['id']) {
        // Verificar si es un combo de products que necesita migración
        $stmt = $pdo->prepare("SELECT id FROM combos WHERE id = ?");
        $stmt->execute([$data['id']]);
        $existsInCombos = $stmt->fetch();
        
        if (!$existsInCombos) {
            // Migrar de products a combos
            $stmt = $pdo->prepare("SELECT name, description, price, image_url FROM products WHERE id = ? AND category_id = 8");
            $stmt->execute([$data['id']]);
            $productData = $stmt->fetch();
            
            if ($productData) {
                // Crear en tabla combos con el mismo ID
                $stmt = $pdo->prepare("INSERT INTO combos (id, name, description, price, image_url, category_id) VALUES (?, ?, ?, ?, ?, 8)");
                $stmt->execute([$data['id'], $productData['name'], $productData['description'], $productData['price'], $productData['image_url']]);
            }
        }
        
        // Actualizar combo existente
        $stmt = $pdo->prepare("UPDATE combos SET name = ?, description = ?, price = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$data['name'], $data['description'], $data['price'], $data['image_url'], $data['id']]);
        $combo_id = $data['id'];
        
        // Eliminar items y selections existentes
        $stmt = $pdo->prepare("DELETE FROM combo_items WHERE combo_id = ?");
        $stmt->execute([$combo_id]);
        $stmt = $pdo->prepare("DELETE FROM combo_selections WHERE combo_id = ?");
        $stmt->execute([$combo_id]);
    } else {
        // Crear nuevo combo
        $stmt = $pdo->prepare("INSERT INTO combos (name, description, price, image_url, category_id) VALUES (?, ?, ?, ?, 8)");
        $stmt->execute([$data['name'], $data['description'], $data['price'], $data['image_url']]);
        $combo_id = $pdo->lastInsertId();
    }
    
    // Agregar items del combo (fixed_items)
    if (isset($data['fixed_items']) && is_array($data['fixed_items'])) {
        $stmt = $pdo->prepare("INSERT INTO combo_items (combo_id, product_id, quantity, is_selectable, selection_group) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($data['fixed_items'] as $item) {
            $stmt->execute([
                $combo_id,
                $item['product_id'],
                $item['quantity'] ?? 1,
                0, // fixed items no son seleccionables
                null
            ]);
        }
    }
    
    // Agregar selections del combo (selection_groups)
    if (isset($data['selection_groups']) && is_array($data['selection_groups']) && count($data['selection_groups']) > 0) {
        $stmt = $pdo->prepare("INSERT INTO combo_selections (combo_id, selection_group, product_id, additional_price, max_selections) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($data['selection_groups'] as $groupName => $groupData) {
            if (isset($groupData['options']) && is_array($groupData['options'])) {
                foreach ($groupData['options'] as $option) {
                    $stmt->execute([
                        $combo_id,
                        $groupName,
                        $option['product_id'],
                        $option['additional_price'] ?? 0,
                        1 // max_selections por defecto
                    ]);
                }
            }
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'combo_id' => $combo_id]);
    
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>