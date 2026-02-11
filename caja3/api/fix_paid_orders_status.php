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

    // Actualizar pedidos pagados que tienen order_status 'completed' a 'sent_to_kitchen'
    $stmt = $pdo->prepare("
        UPDATE tuu_orders 
        SET order_status = 'sent_to_kitchen'
        WHERE payment_status = 'paid' 
        AND order_status = 'completed'
        AND tuu_message = 'Transaccion aprobada'
    ");
    $stmt->execute();
    $updated = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "Se actualizaron $updated pedidos pagados a status inicial 'sent_to_kitchen'"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>