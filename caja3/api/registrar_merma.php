<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
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
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $item_type = $data['item_type'] ?? 'ingredient';
    $item_id = intval($data['item_id'] ?? 0);
    $quantity = floatval($data['quantity'] ?? 0);
    $reason = $data['reason'] ?? 'Merma registrada';
    $user_id = isset($data['user_id']) ? intval($data['user_id']) : null;
    // Smart merma: cantidad_natural = unidades contables (ej: 3 tomates)
    // Si viene, se convierte a la unidad base usando peso_por_unidad
    $cantidad_natural = isset($data['cantidad_natural']) ? intval($data['cantidad_natural']) : null;
    
    if ($item_id <= 0) {
        throw new Exception('ID de item inválido');
    }
    if ($quantity <= 0 && $cantidad_natural === null) {
        throw new Exception('Cantidad inválida');
    }
    
    if ($item_type === 'ingredient') {
        $stmt = $pdo->prepare("SELECT name, unit, cost_per_unit, current_stock as stock, peso_por_unidad, nombre_unidad_natural FROM ingredients WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Ingrediente no encontrado');
        }
        
        // Smart conversion: si viene cantidad_natural y el ingrediente tiene peso_por_unidad
        $peso_por_unidad = floatval($item['peso_por_unidad'] ?? 0);
        if ($cantidad_natural !== null && $cantidad_natural > 0 && $peso_por_unidad > 0) {
            $quantity = $cantidad_natural * $peso_por_unidad;
        }
        
        if ($quantity <= 0) {
            throw new Exception('Cantidad calculada inválida');
        }
        
        $cost = $quantity * floatval($item['cost_per_unit']);
        $unit = $item['unit'];
        
        // Guardar nota de conversión si fue smart
        $nota_conversion = '';
        if ($cantidad_natural !== null && $peso_por_unidad > 0) {
            $nombre_u = $item['nombre_unidad_natural'] ?? 'unidad';
            $nota_conversion = " ({$cantidad_natural} {$nombre_u}" . ($cantidad_natural > 1 ? 's' : '') . ")";
        }
        $reason_final = $reason . $nota_conversion;
        
        $stmt = $pdo->prepare("INSERT INTO mermas (ingredient_id, item_type, item_name, quantity, unit, cost, reason, user_id) VALUES (?, 'ingredient', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_id, $item['name'], $quantity, $unit, $cost, $reason_final, $user_id]);
        
        $stmt = $pdo->prepare("UPDATE ingredients SET current_stock = GREATEST(current_stock - ?, 0) WHERE id = ?");
        $stmt->execute([$quantity, $item_id]);
    } else {
        $stmt = $pdo->prepare("SELECT name, cost_price, stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Producto no encontrado');
        }
        
        $cost = $quantity * floatval($item['cost_price']);
        $unit = 'unidad';
        
        $stmt = $pdo->prepare("INSERT INTO mermas (product_id, item_type, item_name, quantity, unit, cost, reason, user_id) VALUES (?, 'product', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_id, $item['name'], $quantity, $unit, $cost, $reason, $user_id]);
        
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ?");
        $stmt->execute([$quantity, $item_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Merma registrada exitosamente',
        'merma' => [
            'id' => $pdo->lastInsertId(),
            'item_name' => $item['name'],
            'quantity' => $quantity,
            'unit' => $unit,
            'cost' => $cost,
            'cantidad_natural' => $cantidad_natural
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
