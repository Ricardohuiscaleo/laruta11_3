<?php
// Script para arreglar la sincronización de TUU en producción
// Ejecutar en: https://app.laruta11.cl/api/tuu/fix_sync_production.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // 1. Verificar tabla existe
    $checkTable = "SHOW TABLES LIKE 'tuu_pos_transactions'";
    $stmt = $pdo->prepare($checkTable);
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        // Crear tabla
        $createTable = "
        CREATE TABLE tuu_pos_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id VARCHAR(255) UNIQUE NOT NULL,
            sequence_number VARCHAR(100),
            pos_serial_number VARCHAR(100),
            status VARCHAR(50),
            amount DECIMAL(10,2),
            currency VARCHAR(10) DEFAULT 'CLP',
            transaction_type VARCHAR(50),
            payment_date_time DATETIME,
            items_json JSON,
            extra_data_json JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_payment_date (payment_date_time),
            INDEX idx_status (status),
            INDEX idx_pos_serial (pos_serial_number)
        )";
        $pdo->exec($createTable);
    }
    
    // 2. Crear tabla de control
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
    
    // 3. Sincronizar datos desde septiembre hasta hoy
    $totalImported = 0;
    $totalUpdated = 0;
    $errors = [];
    
    // Procesar por períodos de 7 días
    $startDate = new DateTime('2025-09-01');
    $endDate = new DateTime(); // Hoy
    
    $currentDate = clone $startDate;
    
    while ($currentDate <= $endDate) {
        $weekEnd = clone $currentDate;
        $weekEnd->add(new DateInterval('P6D'));
        
        if ($weekEnd > $endDate) {
            $weekEnd = clone $endDate;
        }
        
        $startDateStr = $currentDate->format('Y-m-d');
        $endDateStr = $weekEnd->format('Y-m-d');
        
        // Llamar API TUU
        $url = 'https://integrations.payment.haulmer.com/Report/get-report';
        
        $requestData = [
            'Filters' => [
                'StartDate' => $startDateStr,
                'EndDate' => $endDateStr
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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            
            if ($responseData && isset($responseData['content']['reports'])) {
                $reports = $responseData['content']['reports'];
                
                foreach ($reports as $report) {
                    $saleId = $report['saleId'];
                    $paymentDateTime = date('Y-m-d H:i:s', strtotime($report['paymentDataTime']));
                    
                    // Verificar si existe
                    $checkSql = "SELECT id FROM tuu_pos_transactions WHERE sale_id = ?";
                    $checkStmt = $pdo->prepare($checkSql);
                    $checkStmt->execute([$saleId]);
                    
                    if ($checkStmt->fetch()) {
                        // Actualizar
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
                        $totalUpdated++;
                    } else {
                        // Insertar
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
                        $totalImported++;
                    }
                }
            }
        } else {
            $errors[] = "Error HTTP $httpCode para $startDateStr - $endDateStr";
        }
        
        $currentDate->add(new DateInterval('P7D'));
        
        // Pausa para no sobrecargar
        if ($currentDate <= $endDate) {
            usleep(200000); // 0.2 segundos
        }
    }
    
    // 4. Actualizar control
    $message = "Sincronización manual completada: $totalImported nuevas, $totalUpdated actualizadas";
    $updateControlSql = "INSERT INTO tuu_sync_control (id, status, message, transactions_synced, last_sync_date, last_sync_time) 
                        VALUES (1, 'completed', ?, ?, CURDATE(), NOW()) 
                        ON DUPLICATE KEY UPDATE 
                        status = 'completed', message = ?, transactions_synced = ?, 
                        last_sync_date = CURDATE(), last_sync_time = NOW()";
    $stmt = $pdo->prepare($updateControlSql);
    $stmt->execute([$message, $totalImported + $totalUpdated, $message, $totalImported + $totalUpdated]);
    
    echo json_encode([
        'success' => true,
        'total_imported' => $totalImported,
        'total_updated' => $totalUpdated,
        'errors' => $errors,
        'sync_time' => date('Y-m-d H:i:s'),
        'period' => '2025-09-01 a ' . date('Y-m-d'),
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>