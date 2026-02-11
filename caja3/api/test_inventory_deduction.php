<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
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
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? 'simulate';

// Simular venta de prueba
if ($action === 'simulate') {
    try {
        $productId = $_POST['product_id'] ?? null;
        $quantity = $_POST['quantity'] ?? 1;
        $paymentMethod = $_POST['payment_method'] ?? 'efectivo';
        
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'product_id requerido']);
            exit;
        }
    
    // 1. Obtener producto
    $stmt = $pdo->prepare("SELECT id, name, price, cost_price, category_id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }
    
    // 2. Obtener inventario ANTES
    $beforeInventory = getInventorySnapshot($pdo, $productId);
    
    // 3. INICIAR TRANSACCIÓN (para poder revertir)
    $pdo->beginTransaction();
    
    // 4. Simular descuento de inventario
    $inventoryResult = simulateInventoryDeduction($pdo, $productId, $product['name'], $quantity);
    
    // 5. Obtener inventario DESPUÉS
    $afterInventory = getInventorySnapshot($pdo, $productId);
    
    // 6. REVERTIR CAMBIOS (ROLLBACK) - El test no afecta la BD real
    $pdo->rollBack();
    
        echo json_encode([
            'success' => true,
            'product' => $product,
            'quantity' => $quantity,
            'payment_method' => $paymentMethod,
            'inventory_before' => $beforeInventory,
            'inventory_after' => $afterInventory,
            'inventory_changes' => calculateChanges($beforeInventory, $afterInventory),
            'deduction_log' => $inventoryResult,
            'test_mode' => true,
            'reverted' => true,
            'message' => 'Test completado. Cambios revertidos automáticamente.'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Listar productos activos para test
if ($action === 'list') {
    $stmt = $pdo->query("SELECT id, name, price, cost_price, category_id FROM products WHERE is_active = 1 ORDER BY category_id, name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

function getInventorySnapshot($pdo, $productId) {
    $snapshot = ['product' => [], 'ingredients' => []];
    
    // Stock del producto
    $stmt = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $snapshot['product'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Stock de ingredientes de la receta
    $stmt = $pdo->prepare("
        SELECT i.id, i.name, i.current_stock as stock_quantity, i.unit, pr.quantity as recipe_qty
        FROM product_recipes pr
        INNER JOIN ingredients i ON pr.ingredient_id = i.id
        WHERE pr.product_id = ?
    ");
    $stmt->execute([$productId]);
    $snapshot['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $snapshot;
}

function simulateInventoryDeduction($pdo, $productId, $productName, $qty) {
    $log = [];
    
    // Descontar ingredientes de la receta (si tiene)
    $stmt = $pdo->prepare("
        SELECT pr.ingredient_id, pr.quantity, pr.unit, i.name
        FROM product_recipes pr
        INNER JOIN ingredients i ON pr.ingredient_id = i.id
        WHERE pr.product_id = ?
    ");
    $stmt->execute([$productId]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recipes) > 0) {
        // Producto con receta: descontar ingredientes
        foreach ($recipes as $recipe) {
            // current_stock está en kg, convertir según unidad de receta
            $deductQty = $recipe['quantity'] * $qty;
            
            if ($recipe['unit'] === 'g') {
                // Receta en gramos → convertir a kg para deducir
                $deductQty = $deductQty / 1000;
            }
            // Si unit='kg' o 'unidad', usar cantidad directa
            
            $stmt = $pdo->prepare("UPDATE ingredients SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->execute([$deductQty, $recipe['ingredient_id']]);
            
            $log[] = [
                'type' => 'ingredient',
                'id' => $recipe['ingredient_id'],
                'name' => $recipe['name'],
                'deducted' => $deductQty,
                'unit' => $recipe['unit']
            ];
        }
    } else {
        // Producto sin receta (ej: bebidas): descontar del stock del producto
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $stmt->execute([$qty, $productId]);
        
        $log[] = [
            'type' => 'product',
            'id' => $productId,
            'name' => $productName,
            'deducted' => $qty,
            'unit' => 'unidad'
        ];
    }
    
    return $log;
}

function calculateChanges($before, $after) {
    $changes = [];
    
    // Cambios en ingredientes
    $changes['ingredients'] = [];
    
    if (count($before['ingredients']) > 0) {
        // Producto con receta: mostrar cambios en ingredientes
        foreach ($before['ingredients'] as $idx => $ing) {
            if (isset($after['ingredients'][$idx])) {
                $changes['ingredients'][] = [
                    'name' => $ing['name'],
                    'before' => $ing['stock_quantity'],
                    'after' => $after['ingredients'][$idx]['stock_quantity'],
                    'change' => $after['ingredients'][$idx]['stock_quantity'] - $ing['stock_quantity'],
                    'unit' => $ing['unit']
                ];
            }
        }
    } else {
        // Producto sin receta: mostrar cambio en stock del producto
        if (isset($before['product']['stock_quantity']) && isset($after['product']['stock_quantity'])) {
            $changes['ingredients'][] = [
                'name' => $before['product']['name'] . ' (Stock Producto)',
                'before' => $before['product']['stock_quantity'],
                'after' => $after['product']['stock_quantity'],
                'change' => $after['product']['stock_quantity'] - $before['product']['stock_quantity'],
                'unit' => 'unidad'
            ];
        }
    }
    
    return $changes;
}
