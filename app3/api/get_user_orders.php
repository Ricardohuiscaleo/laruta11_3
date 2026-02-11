<?php
header('Content-Type: application/json');

$configPaths = ['../config.php', '../../config.php', '../../../config.php', '../../../../config.php'];
$configFound = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config = require $path;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['success' => false, 'error' => 'No se pudo encontrar config.php']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener email del POST request
    $input = json_decode(file_get_contents('php://input'), true);
    $user_email = $input['user_email'] ?? null;
    
    if (!$user_email) {
        echo json_encode(['success' => false, 'error' => 'Email de usuario requerido']);
        exit;
    }
    
    // Buscar user_id por email
    $emailStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $emailStmt->execute([$user_email]);
    $userResult = $emailStmt->fetch(PDO::FETCH_ASSOC);
    $user_id = $userResult ? $userResult['id'] : null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado', 'email_searched' => $user_email]);
        exit;
    }
    
    // Obtener pedidos del usuario
    $stmt = $pdo->prepare("
        SELECT 
            id,
            order_number as order_reference,
            product_name,
            installment_amount as amount,
            order_status,
            payment_status,
            payment_method,
            customer_phone,
            created_at,
            CASE 
                WHEN order_status = 'cancelled' THEN 'Cancelado'
                WHEN payment_status = 'paid' AND order_status = 'delivered' THEN 'Entregado'
                WHEN payment_status = 'unpaid' THEN 'Pendiente'
                ELSE 'En proceso'
            END as status_display
        FROM tuu_orders 
        WHERE user_id = ? AND payment_status = 'paid' AND order_status != 'cancelled'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener items detallados para cada pedido
    foreach ($orders as &$order) {
        $itemsStmt = $pdo->prepare("
            SELECT 
                product_name,
                quantity,
                product_price,
                combo_data
            FROM tuu_order_items 
            WHERE order_id = ?
        ");
        $itemsStmt->execute([$order['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar combo_data si existe
        foreach ($items as &$item) {
            if ($item['combo_data']) {
                $item['combo_data'] = json_decode($item['combo_data'], true);
            }
        }
        
        $order['items'] = $items;
        unset($order['id']); // No exponer el ID interno
    }
    
    // Calcular estadísticas (solo pedidos pagados, excluir delivery_fee)
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(installment_amount - COALESCE(delivery_fee, 0)) as total_spent
        FROM tuu_orders 
        WHERE user_id = ? AND payment_status = 'paid' AND order_status != 'cancelled'
    ");
    $statsStmt->execute([$user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al obtener pedidos', 'debug' => $e->getMessage()]);
}
?>