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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;
$order_status = $input['order_status'] ?? null;
$payment_status = $input['payment_status'] ?? null;

if (!$order_id || (!$order_status && !$payment_status)) {
    echo json_encode(['success' => false, 'error' => 'Datos requeridos faltantes']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener datos del pedido para notificación
    $stmt = $pdo->prepare("SELECT order_number, customer_name FROM tuu_orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $orderData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order_status) {
        $stmt = $pdo->prepare("UPDATE tuu_orders SET order_status = ? WHERE id = ?");
        $stmt->execute([$order_status, $order_id]);
        
        // Enviar notificación al cliente
        if ($orderData) {
            sendOrderNotification($order_id, $orderData['order_number'], $orderData['customer_name'], $order_status, $config);
        }
    }
    
    if ($payment_status) {
        $stmt = $pdo->prepare("UPDATE tuu_orders SET payment_status = ? WHERE id = ?");
        $stmt->execute([$payment_status, $order_id]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar estado']);
}

function sendOrderNotification($orderId, $orderNumber, $customerName, $status, $config) {
    $statusMessages = [
        'sent_to_kitchen' => '👨🍳 Tu pedido está siendo preparado en cocina',
        'preparing' => '🔥 Estamos cocinando tu pedido con mucho amor',
        'ready' => '✅ ¡Tu pedido está listo para retirar!',
        'delivered' => '🎉 Pedido entregado. ¡Gracias por elegirnos!'
    ];

    $message = $statusMessages[$status] ?? 'Estado de pedido actualizado';
    
    try {
        $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
        if ($conn->connect_error) return;
        
        $stmt = $conn->prepare("INSERT INTO order_notifications (order_id, order_number, customer_name, status, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issss', $orderId, $orderNumber, $customerName, $status, $message);
        $stmt->execute();
        $conn->close();
    } catch (Exception $e) {
        error_log('Error guardando notificación: ' . $e->getMessage());
    }
}
?>