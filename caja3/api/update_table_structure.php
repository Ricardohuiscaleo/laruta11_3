<?php
// Script para actualizar estructura de tabla tuu_orders
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

    // Agregar nuevas columnas
    $alterQueries = [
        "ALTER TABLE tuu_orders ADD COLUMN payment_status ENUM('unpaid', 'pending_payment', 'paid', 'failed') DEFAULT 'unpaid' AFTER status",
        "ALTER TABLE tuu_orders ADD COLUMN order_status ENUM('pending', 'sent_to_kitchen', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'completed') DEFAULT 'pending' AFTER payment_status",
        "ALTER TABLE tuu_orders ADD COLUMN delivery_type ENUM('pickup', 'delivery') DEFAULT 'pickup' AFTER order_status",
        "ALTER TABLE tuu_orders ADD COLUMN delivery_address TEXT NULL AFTER delivery_type",
        "ALTER TABLE tuu_orders ADD COLUMN rider_id INT NULL AFTER delivery_address",
        "ALTER TABLE tuu_orders ADD COLUMN estimated_delivery_time TIMESTAMP NULL AFTER rider_id"
    ];

    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
            echo "✅ Ejecutado: " . substr($query, 0, 50) . "...\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⚠️ Columna ya existe: " . substr($query, 0, 50) . "...\n";
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }

    // Migrar datos existentes
    $pdo->exec("
        UPDATE tuu_orders SET 
            payment_status = CASE 
                WHEN tuu_transaction_id IS NOT NULL AND tuu_message = 'Transaccion aprobada' THEN 'paid'
                WHEN tuu_transaction_id IS NULL THEN 'unpaid'
                ELSE 'pending_payment'
            END,
            order_status = CASE 
                WHEN status = 'pending' THEN 'pending'
                WHEN status = 'sent_to_pos' THEN 'sent_to_kitchen'
                WHEN status = 'preparing' THEN 'preparing'
                WHEN status = 'ready' THEN 'ready'
                WHEN status = 'completed' THEN 'completed'
                ELSE 'pending'
            END
        WHERE payment_status IS NULL OR order_status IS NULL
    ");

    echo json_encode(['success' => true, 'message' => 'Estructura actualizada correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>