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

function sendNotification($orderId, $orderNumber, $customerName, $status, $config) {
    $statusMessages = [
        'sent_to_kitchen' => '👨‍🍳 Tu pedido está siendo preparado en cocina',
        'preparing' => '🔥 Estamos cocinando tu pedido con mucho amor',
        'ready' => '✅ ¡Tu pedido está listo para retirar!',
        'delivered' => '🎉 Pedido entregado. ¡Gracias por elegirnos!'
    ];

    $message = $statusMessages[$status] ?? 'Estado de pedido actualizado';
    
    // Insertar notificación en Supabase
    $supabaseUrl = $config['PUBLIC_SUPABASE_URL'];
    $supabaseKey = $config['PUBLIC_SUPABASE_ANON_KEY'];
    
    $notificationData = [
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'customer_name' => $customerName,
        'status' => $status,
        'message' => $message,
        'created_at' => date('c')
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabaseUrl . '/rest/v1/order_notifications');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 201;
}

// Función para ser llamada desde update_order_status.php
if (isset($_POST['order_id']) && isset($_POST['status'])) {
    $orderId = $_POST['order_id'];
    $status = $_POST['status'];
    $orderNumber = $_POST['order_number'] ?? '';
    $customerName = $_POST['customer_name'] ?? '';
    
    $success = sendNotification($orderId, $orderNumber, $customerName, $status, $config);
    echo json_encode(['success' => $success]);
}
?>