<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../db_connect.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $pdo = require __DIR__ . '/../db_connect.php';

    $user_id = (int)$_GET['id'];

    // 1. Obtener datos del usuario (mapeando a lo que el frontend espera)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nombre as name,
            email,
            telefono as phone,
            fecha_registro as registration_date,
            ultimo_acceso as last_login,
            total_spent,
            activo as is_active,
            rut,
            grado_militar,
            unidad_trabajo,
            domicilio_particular,
            es_militar_rl6,
            credito_aprobado,
            limite_credito,
            credito_usado,
            credito_disponible,
            selfie_url,
            carnet_frontal_url,
            carnet_trasero_url
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // 2. Obtener pedidos recientes del usuario (de tuu_orders)
    $stmtOrder = $pdo->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.payment_status as status,
            o.product_price as total_amount,
            o.created_at,
            o.product_name as items
        FROM tuu_orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmtOrder->execute([$user_id]);
    $orders = $stmtOrder->fetchAll(PDO::FETCH_ASSOC);

    // 3. Estadísticas rápidas
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(product_price) as total_spent,
            AVG(product_price) as avg_order_value,
            MAX(created_at) as last_order_date
        FROM tuu_orders 
        WHERE user_id = ? AND payment_status = 'paid'
    ");
    $stmtStats->execute([$user_id]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'orders' => $orders,
            'stats' => $stats
        ]
    ]);

}
catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}