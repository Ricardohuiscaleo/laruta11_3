<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$debug = [];
$debug[] = 'Script iniciado';

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
    echo json_encode(['success' => false, 'error' => 'Config no encontrado', 'debug' => $debug]);
    exit;
}
$debug[] = 'Config encontrado';

try {
    $debug[] = 'Conectando a DB';
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $debug[] = 'Conexión exitosa';
    
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 4;
    $debug[] = 'Días: ' . $days;
    
    // Ventas últimos 30 días
    $sql = "
        SELECT 
            oi.product_id,
            oi.product_name,
            SUM(oi.quantity) as total_sold,
            COUNT(DISTINCT DATE(o.created_at)) as days_with_sales
        FROM tuu_order_items oi
        INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
        WHERE DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY oi.product_id, oi.product_name
    ";
    
    $stmt = $pdo->query($sql);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug[] = 'Ventas: ' . count($sales);
    
    $ingredients_needed = [];
    $products_forecast = [];
    $direct_products = []; // Productos sin receta (bebidas, etc.)
    
    foreach ($sales as $sale) {
        $avg_daily = $sale['total_sold'] / max(1, $sale['days_with_sales']);
        $forecast = ceil($avg_daily * $days);
        
        $products_forecast[] = [
            'product_name' => $sale['product_name'],
            'avg_daily' => round($avg_daily, 2),
            'forecast_qty' => $forecast,
            'days' => $days
        ];
        
        // Receta con JOIN a ingredients
        $recipe_sql = "
            SELECT 
                pr.ingredient_id,
                i.name as ingredient_name,
                pr.unit,
                i.cost_per_unit,
                i.current_stock,
                pr.quantity
            FROM product_recipes pr
            INNER JOIN ingredients i ON pr.ingredient_id = i.id
            WHERE pr.product_id = ?
        ";
        $recipe_stmt = $pdo->prepare($recipe_sql);
        $recipe_stmt->execute([$sale['product_id']]);
        $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si no tiene receta, es un producto directo (bebida, jugo, etc.)
        if (empty($recipe)) {
            // Obtener info del producto
            $product_sql = "SELECT id, name, cost_price, stock_quantity FROM products WHERE id = ?";
            $product_stmt = $pdo->prepare($product_sql);
            $product_stmt->execute([$sale['product_id']]);
            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $to_buy = max(0, $forecast - floatval($product['stock_quantity']));
                if ($to_buy > 0) {
                    $direct_products[] = [
                        'product_id' => $product['id'],
                        'product_name' => $product['name'],
                        'current_stock' => floatval($product['stock_quantity']),
                        'needed' => $forecast,
                        'to_buy' => $to_buy,
                        'unit' => 'unidad',
                        'cost_per_unit' => floatval($product['cost_price']),
                        'total_cost' => $to_buy * floatval($product['cost_price'])
                    ];
                }
            }
        } else {
            // Producto con receta (completos, hamburguesas, etc.)
            foreach ($recipe as $item) {
                $key = $item['ingredient_id'];
                $quantity = floatval($item['quantity']);
                
                // Si la receta está en gramos pero el ingrediente se almacena en kg, convertir
                if ($item['unit'] === 'g') {
                    $quantity = $quantity / 1000; // Convertir gramos a kg
                }
                
                $needed = $quantity * $forecast;
                
                if (!isset($ingredients_needed[$key])) {
                    // Usar la unidad real del ingrediente (kg), no la de la receta (g)
                    $actualUnit = $item['unit'] === 'g' ? 'kg' : $item['unit'];
                    
                    $ingredients_needed[$key] = [
                        'ingredient_id' => $key,
                        'ingredient_name' => $item['ingredient_name'],
                        'unit' => $actualUnit,
                        'cost_per_unit' => floatval($item['cost_per_unit']),
                        'current_stock' => floatval($item['current_stock']),
                        'total_needed' => 0,
                        'products_using' => []
                    ];
                }
                
                $ingredients_needed[$key]['total_needed'] += $needed;
                $ingredients_needed[$key]['products_using'][] = [
                    'product' => $sale['product_name'],
                    'qty_needed' => $needed
                ];
            }
        }
    }
    
    $purchase_list = [];
    $total_cost = 0;
    
    // Agregar ingredientes
    foreach ($ingredients_needed as $ing) {
        $to_buy = max(0, $ing['total_needed'] - $ing['current_stock']);
        
        if ($to_buy <= 0) continue;
        
        $cost = $to_buy * $ing['cost_per_unit'];
        $total_cost += $cost;
        
        $purchase_list[] = [
            'ingredient_name' => $ing['ingredient_name'],
            'current_stock' => round($ing['current_stock'], 2),
            'needed' => round($ing['total_needed'], 2),
            'to_buy' => round($to_buy, 2),
            'unit' => $ing['unit'],
            'cost_per_unit' => $ing['cost_per_unit'],
            'total_cost' => $cost,
            'products_using' => $ing['products_using'],
            'type' => 'ingredient'
        ];
    }
    
    // Agregar productos directos (bebidas, jugos, etc.)
    foreach ($direct_products as $prod) {
        $total_cost += $prod['total_cost'];
        $purchase_list[] = [
            'ingredient_name' => $prod['product_name'],
            'current_stock' => $prod['current_stock'],
            'needed' => $prod['needed'],
            'to_buy' => $prod['to_buy'],
            'unit' => $prod['unit'],
            'cost_per_unit' => $prod['cost_per_unit'],
            'total_cost' => $prod['total_cost'],
            'products_using' => [],
            'type' => 'product'
        ];
    }
    
    // Priorizar por categoría y rotación
    usort($purchase_list, function($a, $b) {
        // Definir prioridades por categoría
        $priorities = [
            'proteína' => 1, 'carne' => 1, 'pollo' => 1, 'cerdo' => 1,
            'vegetal' => 2, 'verdura' => 2, 'tomate' => 2, 'lechuga' => 2,
            'lácteo' => 3, 'queso' => 3, 'leche' => 3,
            'pan' => 4, 'marraqueta' => 4,
            'condimento' => 5, 'salsa' => 5
        ];
        
        $getPriority = function($name) use ($priorities) {
            $name_lower = strtolower($name);
            foreach ($priorities as $keyword => $priority) {
                if (strpos($name_lower, $keyword) !== false) {
                    return $priority;
                }
            }
            return 6; // Default
        };
        
        $priorityA = $getPriority($a['ingredient_name']);
        $priorityB = $getPriority($b['ingredient_name']);
        
        // Primero por prioridad de categoría
        if ($priorityA !== $priorityB) {
            return $priorityA <=> $priorityB;
        }
        
        // Luego por rotación (cantidad necesaria vs stock)
        $rotationA = $a['current_stock'] > 0 ? $a['needed'] / $a['current_stock'] : 999;
        $rotationB = $b['current_stock'] > 0 ? $b['needed'] / $b['current_stock'] : 999;
        
        return $rotationB <=> $rotationA;
    });
    
    $debug[] = 'Ingredientes: ' . count($ingredients_needed);
    $debug[] = 'Productos directos: ' . count($direct_products);
    $debug[] = 'Total items: ' . count($purchase_list);
    
    echo json_encode([
        'success' => true,
        'debug' => $debug,
        'data' => [
            'days' => $days,
            'products_forecast' => $products_forecast,
            'purchase_list' => $purchase_list,
            'total_cost' => $total_cost,
            'summary' => [
                'total_ingredients' => count($purchase_list),
                'total_products' => count($products_forecast)
            ]
        ]
    ]);
    
} catch (Exception $e) {
    $debug[] = 'ERROR: ' . $e->getMessage();
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => $debug]);
}
