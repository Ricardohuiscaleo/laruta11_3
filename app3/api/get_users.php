<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';
$config = require '../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.registration_date,
            u.last_login,
            u.is_active,
            u.total_orders,
            u.total_spent,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount_spent
        FROM app_users u
        LEFT JOIN user_orders o ON u.id = o.user_id AND o.status != 'cancelled'
        GROUP BY u.id
        ORDER BY u.registration_date DESC
    ");
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>