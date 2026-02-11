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

    // Corregir solo los pedidos pendientes (ID 37, 38)
    $stmt = $pdo->prepare("
        UPDATE tuu_orders 
        SET order_status = 'pending' 
        WHERE id IN (37, 38) AND payment_status = 'unpaid'
    ");
    $stmt->execute();
    
    // Verificar que los datos estén correctos
    $stmt = $pdo->prepare("
        SELECT id, order_number, payment_status, order_status 
        FROM tuu_orders 
        WHERE id IN (37, 38)
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'orders' => $results]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>