<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$config = require '../config.php';

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

$customer_phone = $_GET['customer_phone'] ?? '';

$stmt = $conn->prepare("
    SELECT n.* 
    FROM order_notifications n
    JOIN tuu_orders o ON n.order_id = o.id
    WHERE o.customer_phone = ? 
    ORDER BY n.created_at DESC
    LIMIT 10
");
$stmt->bind_param('s', $customer_phone);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => count($notifications)
]);

$conn->close();
?>