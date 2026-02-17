<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

// Obtener datos del usuario
$stmt = $conn->prepare("
    SELECT nombre, email, grado_militar, unidad_trabajo, limite_credito, credito_usado, fecha_aprobacion_rl6
    FROM usuarios 
    WHERE id = ? AND es_militar_rl6 = 1 AND credito_aprobado = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

// Obtener transacciones de rl6_credit_transactions
$stmt = $conn->prepare("
    SELECT 
        id,
        amount,
        type,
        description,
        order_id,
        created_at
    FROM rl6_credit_transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $items = [];
    
    // Si tiene order_id, obtener items de la venta y datos de delivery
    if ($row['order_id']) {
        // Items de productos
        $stmt_items = $conn->prepare("
            SELECT product_name, quantity, product_price, combo_data
            FROM tuu_order_items
            WHERE order_reference = ?
        ");
        $stmt_items->bind_param("s", $row['order_id']);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        
        while ($item = $result_items->fetch_assoc()) {
            $items[] = [
                'nombre' => $item['product_name'],
                'cantidad' => intval($item['quantity']),
                'precio' => floatval($item['product_price'])
            ];
            
            // Parsear customizaciones si existen
            if ($item['combo_data']) {
                // Decodificar HTML entities primero
                $decoded = html_entity_decode($item['combo_data'], ENT_QUOTES, 'UTF-8');
                $combo_data = json_decode($decoded, true);
                if (isset($combo_data['customizations']) && is_array($combo_data['customizations'])) {
                    foreach ($combo_data['customizations'] as $custom) {
                        $items[] = [
                            'nombre' => '  + ' . ($custom['name'] ?? 'Extra'),
                            'cantidad' => intval($custom['quantity'] ?? 1),
                            'precio' => floatval($custom['price'] ?? 0)
                        ];
                    }
                }
            }
        }
        
        // Datos de delivery/extras
        $stmt_order = $conn->prepare("
            SELECT delivery_type, delivery_fee, delivery_discount, delivery_extras, delivery_extras_items, subtotal
            FROM tuu_orders
            WHERE order_number = ?
        ");
        $stmt_order->bind_param("s", $row['order_id']);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        $order_data = $result_order->fetch_assoc();
        
        $delivery_fee = 0;
        $delivery_discount = 0;
        $subtotal_productos = 0;
        
        // Agregar delivery fee si existe
        if ($order_data && floatval($order_data['delivery_fee']) > 0) {
            $delivery_fee = floatval($order_data['delivery_fee']);
            $delivery_discount = floatval($order_data['delivery_discount'] ?? 0);
            $delivery_neto = $delivery_fee - $delivery_discount;
            
            $items[] = [
                'nombre' => 'Delivery (' . ucfirst($order_data['delivery_type']) . ')',
                'cantidad' => 1,
                'precio' => $delivery_neto
            ];
        }
        
        // Calcular subtotal de productos (sin delivery)
        if ($order_data) {
            $subtotal_productos = floatval($order_data['subtotal']) - ($delivery_fee - $delivery_discount);
        }
        
        // Agregar extras de delivery si existen
        if ($order_data && floatval($order_data['delivery_extras']) > 0 && $order_data['delivery_extras_items']) {
            $extras = json_decode($order_data['delivery_extras_items'], true);
            if ($extras && is_array($extras)) {
                foreach ($extras as $extra) {
                    $items[] = [
                        'nombre' => $extra['name'] ?? 'Extra',
                        'cantidad' => 1,
                        'precio' => floatval($extra['price'] ?? 0)
                    ];
                }
            }
        }
    }
    
    $transactions[] = [
        'id' => $row['id'],
        'fecha' => $row['created_at'],
        'monto' => floatval($row['amount']),
        'tipo' => $row['type'],
        'descripcion' => $row['description'],
        'order_id' => $row['order_id'],
        'items' => $items,
        'subtotal_productos' => $subtotal_productos,
        'delivery_fee' => $delivery_fee - $delivery_discount,
        'delivery_discount' => $delivery_discount
    ];
}

// Calcular crédito usado real desde transacciones
// Agrupar por order_id para calcular neto (débito - refund del mismo pedido)
$order_groups = [];
foreach ($transactions as $tx) {
    $key = $tx['order_id'] ?: 'tx_' . $tx['id'];
    if (!isset($order_groups[$key])) {
        $order_groups[$key] = [];
    }
    $order_groups[$key][] = $tx;
}

$credito_usado_real = 0;
foreach ($order_groups as $group) {
    $neto = 0;
    foreach ($group as $tx) {
        if ($tx['tipo'] === 'debit') {
            $neto += $tx['monto'];
        } else if ($tx['tipo'] === 'refund' || $tx['tipo'] === 'credit') {
            $neto -= $tx['monto'];
        }
    }
    // Solo sumar si el neto es positivo (compra real no reembolsada)
    if ($neto > 0) {
        $credito_usado_real += $neto;
    }
}

echo json_encode([
    'success' => true,
    'user' => [
        'nombre' => $user['nombre'],
        'email' => $user['email'],
        'grado_militar' => $user['grado_militar'],
        'unidad_trabajo' => $user['unidad_trabajo'],
        'credito_total' => floatval($user['limite_credito']),
        'credito_usado' => $credito_usado_real,
        'credito_disponible' => floatval($user['limite_credito']) - $credito_usado_real,
        'fecha_aprobacion' => $user['fecha_aprobacion_rl6']
    ],
    'transactions' => $transactions,
    'total_transactions' => count($transactions)
]);

$conn->close();
?>
