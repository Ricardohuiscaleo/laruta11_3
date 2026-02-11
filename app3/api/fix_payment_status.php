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

    // Corregir payment_status basado en datos reales
    $stmt = $pdo->prepare("
        UPDATE tuu_orders SET 
            payment_status = CASE 
                WHEN tuu_message = 'Transaccion aprobada' AND tuu_transaction_id IS NOT NULL THEN 'paid'
                WHEN tuu_transaction_id IS NULL AND tuu_amount IS NULL THEN 'unpaid'
                ELSE 'pending_payment'
            END,
            order_status = CASE 
                WHEN status = 'completed' THEN 'completed'
                WHEN status = 'ready' THEN 'ready'
                WHEN status = 'preparing' THEN 'preparing'
                WHEN status = 'sent_to_pos' THEN 'sent_to_kitchen'
                ELSE 'pending'
            END
    ");
    $stmt->execute();
    
    // Verificar resultados
    $stmt = $pdo->prepare("SELECT id, order_number, payment_status, order_status, tuu_message FROM tuu_orders ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'updated_orders' => $results]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>