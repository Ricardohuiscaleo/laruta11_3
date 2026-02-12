<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

// Conectar a BD correcta (u958525313_app)
$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

$user_id = $_SESSION['user']['id'];

try {
    // Obtener pedidos del usuario desde tuu_orders
    $orders_sql = "
        SELECT 
            o.order_number as order_reference,
            o.installment_amount as amount,
            o.payment_method,
            o.order_status as status,
            o.payment_status,
            o.customer_name,
            o.customer_phone,
            o.created_at,
            o.updated_at as completed_at,
            CASE 
                WHEN o.order_status = 'delivered' THEN '✅ Entregado'
                WHEN o.order_status = 'sent_to_kitchen' THEN '📦 En preparación'
                WHEN o.order_status = 'ready' THEN '✅ Listo'
                WHEN o.order_status = 'cancelled' THEN '🚫 Cancelado'
                ELSE '⏱️ Pendiente'
            END as status_display
        FROM tuu_orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 20
    ";
    
    $stmt = mysqli_prepare($conn, $orders_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orders_raw = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Obtener items de cada pedido
    $orders = [];
    foreach ($orders_raw as $order) {
        $items = [];
        if ($order['order_reference']) {
            $items_sql = "SELECT * FROM tuu_order_items WHERE order_reference = ?";
            $stmt_items = mysqli_prepare($conn, $items_sql);
            mysqli_stmt_bind_param($stmt_items, "s", $order['order_reference']);
            mysqli_stmt_execute($stmt_items);
            $result_items = mysqli_stmt_get_result($stmt_items);
            $items = mysqli_fetch_all($result_items, MYSQLI_ASSOC);
            
            // Decodificar combo_data si existe
            foreach ($items as &$item) {
                if (!empty($item['combo_data'])) {
                    $item['combo_data'] = json_decode($item['combo_data'], true);
                }
            }
        }
        
        $order['items'] = $items;
        $orders[] = $order;
    }
    
    // Estadísticas del usuario
    $stats_sql = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'paid' THEN installment_amount ELSE 0 END) as total_spent,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as completed_orders,
            AVG(CASE WHEN payment_status = 'paid' THEN installment_amount ELSE NULL END) as avg_order_amount,
            MAX(created_at) as last_order_date
        FROM tuu_orders 
        WHERE user_id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>