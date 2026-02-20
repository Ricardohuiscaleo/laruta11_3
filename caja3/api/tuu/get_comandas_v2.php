<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Filtros opcionales por customer_name o user_id
    $customer_name = $_GET['customer_name'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    
    $where_clause = "WHERE order_status NOT IN ('delivered', 'cancelled') AND order_number NOT LIKE 'RL6-%'";
    
    if ($user_id) {
        $where_clause .= " AND user_id = :user_id";
    } elseif ($customer_name) {
        $where_clause .= " AND customer_name = :customer_name";
    }
    
    $sql = "SELECT id, order_number, user_id, customer_name, customer_phone, table_number, 
                   product_name, has_item_details, product_price, installments_total, 
                   installment_current, installment_amount, tuu_payment_request_id, 
                   tuu_idempotency_key, tuu_device_used, status, payment_status, payment_method, order_status, 
                   delivery_type, delivery_address, pickup_time, customer_notes, 
                   subtotal, discount_amount, discount_10, discount_30, discount_birthday, discount_pizza, 
                   delivery_discount, delivery_extras, delivery_extras_items, cashback_used,
                   special_instructions, rider_id, estimated_delivery_time, created_at, updated_at, 
                   tuu_transaction_id, tuu_amount, tuu_timestamp, tuu_message, 
                   tuu_account_id, tuu_currency, tuu_signature, delivery_fee, 
                   scheduled_time, is_scheduled, reward_used, reward_stamps_consumed, reward_applied_at,
                   dispatch_photo_url
            FROM tuu_orders 
            {$where_clause}
            ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    
    if ($user_id) {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    } elseif ($customer_name) {
        $stmt->bindParam(':customer_name', $customer_name);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener items de cada pedido con imágenes y recetas
    foreach ($orders as &$order) {
        $items_stmt = $pdo->prepare("
            SELECT 
                oi.id, 
                oi.product_id, 
                oi.product_name, 
                oi.quantity, 
                oi.product_price, 
                oi.item_type, 
                oi.combo_data,
                p.image_url,
                p.category_id,
                p.description
            FROM tuu_order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $items_stmt->execute([$order['id']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener ingredientes de la receta para cada item
        foreach ($items as &$item) {
            // Enriquecer combo_data con imágenes de selections
            if (!empty($item['combo_data'])) {
                $comboData = json_decode($item['combo_data'], true);
                if (isset($comboData['selections'])) {
                    foreach ($comboData['selections'] as $group => $selection) {
                        if (is_array($selection) && isset($selection[0])) {
                            // Si es array de selecciones
                            foreach ($selection as $idx => $sel) {
                                if (isset($sel['id'])) {
                                    $imgStmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                                    $imgStmt->execute([$sel['id']]);
                                    $imgResult = $imgStmt->fetch(PDO::FETCH_ASSOC);
                                    if ($imgResult) {
                                        $comboData['selections'][$group][$idx]['image_url'] = $imgResult['image_url'];
                                    }
                                }
                            }
                        } elseif (isset($selection['id'])) {
                            // Si es una sola selección
                            $imgStmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                            $imgStmt->execute([$selection['id']]);
                            $imgResult = $imgStmt->fetch(PDO::FETCH_ASSOC);
                            if ($imgResult) {
                                $comboData['selections'][$group]['image_url'] = $imgResult['image_url'];
                            }
                        }
                    }
                    $item['combo_data'] = json_encode($comboData);
                }
            }
            
            if ($item['product_id']) {
                $recipeSql = "SELECT i.name, pr.quantity, pr.unit 
                             FROM product_recipes pr 
                             JOIN ingredients i ON pr.ingredient_id = i.id 
                             WHERE pr.product_id = ? AND i.is_active = 1
                             ORDER BY i.name";
                $recipeStmt = $pdo->prepare($recipeSql);
                $recipeStmt->execute([$item['product_id']]);
                $ingredients = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Crear descripción con ingredientes y cantidades
                if (!empty($ingredients)) {
                    $ingredientNames = array_map(function($ing) {
                        $qty = intval($ing['quantity']); // Sin decimales
                        $unit = strtolower($ing['unit']);
                        return $ing['name'] . ' (' . $qty . $unit . ')';
                    }, $ingredients);
                    $item['recipe_description'] = implode(', ', $ingredientNames);
                }
            }
        }
        
        $order['items'] = $items;
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
