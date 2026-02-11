<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = $input['product_id'] ?? 9; // Hamburguesa Clásica por defecto
    $customer_name = $input['customer_name'] ?? 'Cliente Cumpleaños';
    
    // Obtener producto y su costo
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }
    
    // Obtener receta del producto
    $recipe_stmt = $pdo->prepare("
        SELECT pr.*, i.name as ingredient_name, i.current_stock, i.unit, i.cost_per_unit
        FROM product_recipes pr
        JOIN ingredients i ON pr.ingredient_id = i.id
        WHERE pr.product_id = ?
    ");
    $recipe_stmt->execute([$product_id]);
    $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular costo total
    $total_cost = 0;
    foreach ($recipe as $ingredient) {
        $total_cost += $ingredient['quantity_needed'] * $ingredient['cost_per_unit'];
    }
    
    // Registrar orden con precio $0 pero con costo
    $order_number = 'BDAY-' . date('YmdHis');
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, customer_name, total_amount, cost_amount, payment_method, status, created_at)
        VALUES (?, ?, 0, ?, 'cumpleanos', 'completed', NOW())
    ");
    $stmt->execute([$order_number, $customer_name, $total_cost]);
    $order_id = $pdo->lastInsertId();
    
    // Registrar item de orden
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, price, cost)
        VALUES (?, ?, ?, 1, 0, ?)
    ");
    $stmt->execute([$order_id, $product_id, $product['name'], $total_cost]);
    
    // Descontar ingredientes del inventario
    foreach ($recipe as $ingredient) {
        $stmt = $pdo->prepare("
            UPDATE ingredients 
            SET current_stock = current_stock - ? 
            WHERE id = ?
        ");
        $stmt->execute([$ingredient['quantity_needed'], $ingredient['ingredient_id']]);
    }
    
    echo json_encode([
        'success' => true,
        'order_number' => $order_number,
        'product_name' => $product['name'],
        'cost' => $total_cost,
        'message' => '¡Hamburguesa de cumpleaños registrada! 🎂'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
