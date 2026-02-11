<?php
// Script de sincronización diaria para cron job
// Ejecutar diariamente a las 23:30: 30 23 * * * /usr/bin/php /path/to/daily_sync.php

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    error_log('TUU Sync Error: config.php no encontrado');
    exit(1);
}

$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Crear tabla de control si no existe
    $createControlTable = "
    CREATE TABLE IF NOT EXISTS tuu_sync_control (
        id INT PRIMARY KEY DEFAULT 1,
        last_sync_date DATE,
        last_sync_time DATETIME,
        status ENUM('running', 'completed', 'error') DEFAULT 'completed',
        message TEXT,
        transactions_synced INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($createControlTable);
    
    // Verificar si ya hay un sync en progreso
    $checkSql = "SELECT status FROM tuu_sync_control WHERE id = 1";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute();
    $control = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($control && $control['status'] === 'running') {
        error_log('TUU Sync: Ya hay una sincronización en progreso');
        exit(0);
    }
    
    // Marcar como en progreso
    $updateControlSql = "INSERT INTO tuu_sync_control (id, status, message, last_sync_time) 
                        VALUES (1, 'running', 'Iniciando sincronización...', NOW()) 
                        ON DUPLICATE KEY UPDATE 
                        status = 'running', message = 'Iniciando sincronización...', last_sync_time = NOW()";
    $pdo->exec($updateControlSql);
    
    // Sincronizar últimos 3 días EXCLUYENDO HOY (TUU no permite reportes del día actual)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $threeDaysAgo = date('Y-m-d', strtotime('-3 days'));
    
    $url = 'https://integrations.payment.haulmer.com/Report/get-report';
    
    $requestData = [
        'Filters' => [
            'StartDate' => $threeDaysAgo,
            'EndDate' => $yesterday
        ],
        'page' => 1,
        'pageSize' => 100
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . $config['tuu_api_key']
        ],
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error HTTP $httpCode en API TUU");
    }
    
    $responseData = json_decode($response, true);
    
    if (!$responseData || !isset($responseData['content']['reports'])) {
        throw new Exception('Respuesta inválida de API TUU');
    }
    
    $reports = $responseData['content']['reports'];
    $newTransactions = 0;
    $updatedTransactions = 0;
    
    foreach ($reports as $report) {
        $saleId = $report['saleId'];
        $paymentDateTime = date('Y-m-d H:i:s', strtotime($report['paymentDataTime']));
        
        // Verificar si ya existe
        $checkSql = "SELECT id FROM tuu_pos_transactions WHERE sale_id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$saleId]);
        
        if ($checkStmt->fetch()) {
            // Actualizar existente
            $updateSql = "UPDATE tuu_pos_transactions SET 
                sequence_number = ?, pos_serial_number = ?, status = ?, 
                amount = ?, currency = ?, transaction_type = ?, 
                payment_date_time = ?, items_json = ?, extra_data_json = ?, 
                updated_at = NOW()
                WHERE sale_id = ?";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $report['sequenceNumber'] ?? null,
                $report['posSerialNumber'] ?? null,
                $report['status'] ?? null,
                $report['amount'] ?? 0,
                $report['currency'] ?? 'CLP',
                $report['typeTransaction'] ?? null,
                $paymentDateTime,
                json_encode($report['extraData']['items'] ?? []),
                json_encode($report['extraData'] ?? []),
                $saleId
            ]);
            $updatedTransactions++;
        } else {
            // Insertar nuevo
            $insertSql = "INSERT INTO tuu_pos_transactions (
                sale_id, sequence_number, pos_serial_number, status, 
                amount, currency, transaction_type, payment_date_time, 
                items_json, extra_data_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $saleId,
                $report['sequenceNumber'] ?? null,
                $report['posSerialNumber'] ?? null,
                $report['status'] ?? null,
                $report['amount'] ?? 0,
                $report['currency'] ?? 'CLP',
                $report['typeTransaction'] ?? null,
                $paymentDateTime,
                json_encode($report['extraData']['items'] ?? []),
                json_encode($report['extraData'] ?? [])
            ]);
            $newTransactions++;
        }
    }
    
    // Actualizar control como completado
    $message = "Sincronización completada: $newTransactions nuevas, $updatedTransactions actualizadas";
    $updateControlSql = "UPDATE tuu_sync_control SET 
                        status = 'completed', 
                        message = ?, 
                        transactions_synced = ?, 
                        last_sync_date = CURDATE(),
                        last_sync_time = NOW()
                        WHERE id = 1";
    $stmt = $pdo->prepare($updateControlSql);
    $stmt->execute([$message, $newTransactions + $updatedTransactions]);
    
    error_log("TUU Sync Success: $message");
    
} catch (Exception $e) {
    // Marcar como error
    $errorMessage = "Error en sincronización: " . $e->getMessage();
    $updateControlSql = "UPDATE tuu_sync_control SET 
                        status = 'error', 
                        message = ?, 
                        last_sync_time = NOW()
                        WHERE id = 1";
    $stmt = $pdo->prepare($updateControlSql);
    $stmt->execute([$errorMessage]);
    
    error_log("TUU Sync Error: " . $e->getMessage());
}
?>