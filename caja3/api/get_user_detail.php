<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';
$config = require '../config.php';

$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'ID de usuario requerido']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Datos del usuario
    $stmt = $pdo->prepare("SELECT * FROM app_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    // Pedidos del usuario
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            GROUP_CONCAT(
                CONCAT(oi.product_name, ' (', oi.quantity, 'x)')
                SEPARATOR ', '
            ) as items
        FROM user_orders o
        LEFT JOIN user_order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'user' => $user,
        'orders' => $orders
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>