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
$order_number = $input['order_number'] ?? null;
$amount = $input['amount'] ?? null;
$customer_name = $input['customer_name'] ?? null;

if (!$order_number || !$amount) {
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

    // Crear notificación para admin
    $stmt = $pdo->prepare("
        INSERT INTO admin_notifications (title, message, type, created_at) 
        VALUES (?, ?, 'payment', NOW())
    ");
    
    $title = "💰 Nuevo Pago";
    $message = "Pedido #" . $order_number . " - " . $customer_name . " - $" . number_format($amount, 0, ',', '.');
    
    $stmt->execute([$title, $message]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al crear notificación']);
}
?>