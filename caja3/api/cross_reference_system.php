<?php
if (file_exists(__DIR__ . '/../config.php')) {
    $config = require_once __DIR__ . '/../config.php';
} else {
    exit('Config not found');
}

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // PASO 1: Obtener reportes de TUU
    $startDate = '2025-08-20';
    $endDate = '2025-08-29';
    
    $tuuUrl = "https://app.laruta11.cl/api/get_tuu_reports.php?start_date={$startDate}&end_date={$endDate}&page_size=20";
    $tuuResponse = file_get_contents($tuuUrl);
    $tuuData = json_decode($tuuResponse, true);
    
    if (!$tuuData['success']) {
        throw new Exception('No se pudieron obtener reportes de TUU');
    }
    
    // PASO 2: Crear tabla para transacciones TUU si no existe
    $createTableSql = "CREATE TABLE IF NOT EXISTS tuu_real_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id VARCHAR(50) UNIQUE NOT NULL,
        sequence_number VARCHAR(50),
        pos_serial VARCHAR(50),
        status VARCHAR(20),
        amount DECIMAL(10,2),
        currency VARCHAR(10),
        transaction_type VARCHAR(20),
        payment_date TIMESTAMP,
        commission DECIMAL(10,2),
        amount_without_commission DECIMAL(10,2),
        items_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_sale_id (sale_id),
        INDEX idx_sequence (sequence_number),
        INDEX idx_pos_serial (pos_serial),
        INDEX idx_amount (amount),
        INDEX idx_payment_date (payment_date)
    )";
    
    $pdo->exec($createTableSql);
    
    // PASO 3: Insertar/actualizar transacciones TUU
    $inserted = 0;
    $updated = 0;
    
    foreach ($tuuData['data']['reports'] as $report) {
        // Verificar si existe
        $checkSql = "SELECT id FROM tuu_real_transactions WHERE sale_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$report['sale_id']]);
        
        if ($checkStmt->fetch()) {
            // Actualizar
            $updateSql = "UPDATE tuu_real_transactions SET 
                sequence_number = ?,
                pos_serial = ?,
                status = ?,
                amount = ?,
                currency = ?,
                transaction_type = ?,
                payment_date = ?,
                commission = ?,
                amount_without_commission = ?,
                items_json = ?
                WHERE sale_id = ?";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $report['sequence_number'],
                $report['pos_serial'],
                $report['status'],
                $report['amount'],
                $report['currency'],
                $report['transaction_type'],
                $report['payment_date'],
                $report['commission'],
                $report['amount_without_commission'],
                json_encode($report['items']),
                $report['sale_id']
            ]);
            $updated++;
        } else {
            // Insertar
            $insertSql = "INSERT INTO tuu_real_transactions (
                sale_id, sequence_number, pos_serial, status, amount, currency,
                transaction_type, payment_date, commission, amount_without_commission, items_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $report['sale_id'],
                $report['sequence_number'],
                $report['pos_serial'],
                $report['status'],
                $report['amount'],
                $report['currency'],
                $report['transaction_type'],
                $report['payment_date'],
                $report['commission'],
                $report['amount_without_commission'],
                json_encode($report['items'])
            ]);
            $inserted++;
        }
    }
    
    // PASO 4: HACER CRUCE - Conectar pedidos con transacciones reales
    $crossSql = "UPDATE tuu_orders o
                 JOIN tuu_real_transactions t ON (
                     o.tuu_device_used = t.pos_serial 
                     AND ABS(o.installment_amount - t.amount) < 1
                     AND DATE(o.created_at) >= DATE_SUB(DATE(t.payment_date), INTERVAL 1 DAY)
                     AND DATE(o.created_at) <= DATE_ADD(DATE(t.payment_date), INTERVAL 1 DAY)
                 )
                 SET 
                     o.tuu_transaction_id = t.sale_id,
                     o.tuu_sequence_number = t.sequence_number,
                     o.tuu_commission = t.commission,
                     o.tuu_transaction_date = t.payment_date,
                     o.status = CASE 
                         WHEN t.status = 'completed' THEN 'completed'
                         ELSE o.status 
                     END
                 WHERE o.tuu_transaction_id IS NULL";
    
    $crossStmt = $pdo->prepare($crossSql);
    $crossStmt->execute();
    $crossReferences = $crossStmt->rowCount();
    
    // PASO 5: Obtener datos combinados para mostrar
    $combinedSql = "SELECT 
        o.id as order_id,
        o.customer_name,
        o.table_number,
        o.product_name,
        o.installment_amount,
        o.status as order_status,
        o.created_at as order_date,
        t.sale_id,
        t.sequence_number,
        t.amount as tuu_amount,
        t.commission,
        t.payment_date,
        t.status as tuu_status,
        CASE 
            WHEN t.sale_id IS NOT NULL THEN 'CONECTADO'
            ELSE 'SIN CONEXIÓN'
        END as connection_status
    FROM tuu_orders o
    LEFT JOIN tuu_real_transactions t ON o.tuu_transaction_id = t.sale_id
    ORDER BY o.created_at DESC
    LIMIT 10";
    
    $combinedStmt = $pdo->prepare($combinedSql);
    $combinedStmt->execute();
    $combinedData = $combinedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sync_results' => [
            'tuu_transactions_inserted' => $inserted,
            'tuu_transactions_updated' => $updated,
            'cross_references_made' => $crossReferences
        ],
        'combined_data' => $combinedData,
        'codes_used_for_connection' => [
            'pos_serial' => 'Conecta por dispositivo POS',
            'amount' => 'Conecta por monto exacto (±1 peso)',
            'date' => 'Conecta por fecha (±1 día)',
            'result' => 'sale_id de TUU → tuu_transaction_id en pedidos'
        ],
        'message' => "Sincronización completada: $inserted nuevos, $updated actualizados, $crossReferences conexiones hechas"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>