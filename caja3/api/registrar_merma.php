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
    $item_id = $data['item_id'];
    $quantity = $data['quantity'];
    $reason = $data['reason'] ?? 'Merma registrada';
    $user_id = $data['user_id'] ?? null;
    
    if ($item_type === 'ingredient') {
        $stmt = $pdo->prepare("SELECT name, unit, cost_per_unit, current_stock as stock FROM ingredients WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) throw new Exception('Ingrediente no encontrado');
        
        $cost = $quantity * $item['cost_per_unit'];
        $unit = $item['unit'];
        
        $stmt = $pdo->prepare("INSERT INTO mermas (ingredient_id, item_type, item_name, quantity, unit, cost, reason, user_id) VALUES (?, 'ingredient', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_id, $item['name'], $quantity, $unit, $cost, $reason, $user_id]);
        
        $stmt = $pdo->prepare("UPDATE ingredients SET current_stock = current_stock - ? WHERE id = ?");
        $stmt->execute([$quantity, $item_id]);
    } else {
        $stmt = $pdo->prepare("SELECT name, cost_price, stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) throw new Exception('Producto no encontrado');
        
        $cost = $quantity * $item['cost_price'];
        $unit = 'unidad';
        
        $stmt = $pdo->prepare("INSERT INTO mermas (product_id, item_type, item_name, quantity, unit, cost, reason, user_id) VALUES (?, 'product', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_id, $item['name'], $quantity, $unit, $cost, $reason, $user_id]);
        
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
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
            'cost' => $cost
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
