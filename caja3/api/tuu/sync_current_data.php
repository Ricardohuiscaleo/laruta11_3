<?php
// Script para sincronizar datos de TUU desde septiembre hasta diciembre 2025
header('Content-Type: application/json');

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
    
    // Crear tabla si no existe
    $createTable = "
    CREATE TABLE IF NOT EXISTS tuu_pos_transactions (
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
    
    $totalImported = 0;
    $totalUpdated = 0;
    $errors = [];
    
    // Sincronizar desde septiembre hasta diciembre 2025
    $startDate = new DateTime('2025-09-01');
    $endDate = new DateTime('2025-12-27');
    
    // Procesar por semanas para evitar timeouts
    $currentDate = clone $startDate;
    
    while ($currentDate <= $endDate) {
        $weekEnd = clone $currentDate;
        $weekEnd->add(new DateInterval('P6D')); // 6 días = 1 semana
        
        if ($weekEnd > $endDate) {
            $weekEnd = clone $endDate;
        }
        
        $startDateStr = $currentDate->format('Y-m-d');
        $endDateStr = $weekEnd->format('Y-m-d');
        
        echo "Procesando: $startDateStr a $endDateStr\n";
        
        // Llamar a la API de TUU
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
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errors[] = "Error HTTP $httpCode para período $startDateStr - $endDateStr";
            $currentDate->add(new DateInterval('P7D'));
            continue;
        }
        
        $responseData = json_decode($response, true);
        
        if (!$responseData || !isset($responseData['content']['reports'])) {
            $errors[] = "Respuesta inválida para período $startDateStr - $endDateStr";
            $currentDate->add(new DateInterval('P7D'));
            continue;
        }
        
        $reports = $responseData['content']['reports'];
        
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
                $totalUpdated++;
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
                $totalImported++;
            }
        }
        
        // Avanzar a la siguiente semana
        $currentDate->add(new DateInterval('P7D'));
        
        // Pequeña pausa para no sobrecargar la API
        usleep(500000); // 0.5 segundos
    }
    
    echo json_encode([
        'success' => true,
        'total_imported' => $totalImported,
        'total_updated' => $totalUpdated,
        'errors' => $errors,
        'sync_time' => date('Y-m-d H:i:s'),
        'period' => '2025-09-01 a 2025-12-27'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>