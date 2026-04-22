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
    // Accept product_id (new) or combo_id (backward compat)
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
    $combo_id = isset($_GET['combo_id']) ? (int)$_GET['combo_id'] : null;

    // Backward compat: map legacy combo_id → product_id
    if (!$product_id && $combo_id) {
        $legacyMapping = [
            1   => 187,
            2   => 188,
            3   => 190,
            4   => 198,
            211 => 211,
            233 => 233,
            234 => 242,
        ];
        $product_id = $legacyMapping[$combo_id] ?? null;

        // If not in static map, try looking up in legacy combos table
        if (!$product_id) {
            $stmt = $pdo->prepare("
                SELECT p.id FROM combos c
                JOIN products p ON LOWER(TRIM(p.name)) = LOWER(TRIM(c.name))
                WHERE c.id = ? AND p.category_id = 8
                LIMIT 1
            ");
            $stmt->execute([$combo_id]);
            $product_id = $stmt->fetchColumn() ?: null;
        }
    }

    if (!$product_id) {
        // List all active combos (category_id=8)
        $stmt = $pdo->query("
            SELECT id, name, price, image_url, description
            FROM products
            WHERE category_id = 8 AND is_active = 1
            ORDER BY name
        ");
        $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($combos as $combo) {
            $result[] = buildComboResponse($pdo, $combo);
        }

        echo json_encode(['success' => true, 'combos' => $result]);
        exit;
    }

    // Single combo by product_id
    $stmt = $pdo->prepare("
        SELECT id, name, price, image_url, description
        FROM products
        WHERE id = ? AND category_id = 8 AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $combo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$combo) {
        echo json_encode(['success' => false, 'error' => 'Combo not found']);
        exit;
    }

    $comboResponse = buildComboResponse($pdo, $combo);

    echo json_encode([
        'success' => true,
        'combo'   => $comboResponse,
        // Backward compat: also return as combos array
        'combos'  => [$comboResponse]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Build the combo response object from combo_components + products
 */
function buildComboResponse(PDO $pdo, array $combo): array {
    $productId = (int)$combo['id'];

    // Query combo_components JOIN products, filtering by is_active=1
    $stmt = $pdo->prepare("
        SELECT 
            cc.id, cc.child_product_id, p.name AS product_name,
            p.price AS product_price, p.image_url, p.is_active,
            cc.quantity, cc.is_fixed, cc.selection_group,
            cc.max_selections, cc.price_adjustment, cc.sort_order
        FROM combo_components cc
        JOIN products p ON p.id = cc.child_product_id
        WHERE cc.combo_product_id = ?
          AND p.is_active = 1
        ORDER BY cc.is_fixed DESC, cc.selection_group, cc.sort_order, p.name
    ");
    $stmt->execute([$productId]);
    $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fixed_items = [];
    $selection_groups = [];

    foreach ($components as $row) {
        if ((int)$row['is_fixed'] === 1) {
            $fixed_items[] = [
                'product_id'   => (int)$row['child_product_id'],
                'product_name' => $row['product_name'],
                'quantity'     => (int)$row['quantity'],
                'image_url'    => $row['image_url'],
            ];
        } else {
            $group = $row['selection_group'] ?: 'Opciones';
            if (!isset($selection_groups[$group])) {
                $selection_groups[$group] = [
                    'max_selections' => (int)$row['max_selections'],
                    'options'        => [],
                ];
            }
            $selection_groups[$group]['options'][] = [
                'product_id'       => (int)$row['child_product_id'],
                'product_name'     => $row['product_name'],
                'price_adjustment' => (float)$row['price_adjustment'],
                'image_url'        => $row['image_url'],
            ];
        }
    }

    return [
        'id'               => $productId,
        'name'             => $combo['name'],
        'price'            => (int)$combo['price'],
        'image_url'        => $combo['image_url'] ?? null,
        'description'      => $combo['description'] ?? null,
        'fixed_items'      => $fixed_items,
        'selection_groups'  => $selection_groups,
    ];
}
?>
