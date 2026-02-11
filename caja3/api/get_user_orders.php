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
            order_number as order_reference,
            product_name,
            tuu_amount as amount,
            status,
            customer_phone,
            created_at,
            CASE 
                WHEN status = 'completed' THEN 'Completado'
                WHEN status = 'pending' THEN 'Pendiente'
                ELSE 'Cancelado'
            END as status_display,
            'webpay' as payment_method
        FROM tuu_orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'completed' THEN tuu_amount ELSE 0 END) as total_spent
        FROM tuu_orders 
        WHERE user_id = ?
    ");
    $statsStmt->execute([$user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al obtener pedidos', 'debug' => $e->getMessage()]);
}
?>