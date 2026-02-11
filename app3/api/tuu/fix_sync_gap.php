<?php
// Script para recuperar datos faltantes de TUU (septiembre-diciembre 2025)
// Ejecutar en producción: https://app.laruta11.cl/api/tuu/fix_sync_gap.php

header('Content-Type: application/json');

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['error' => 'Config no encontrado']);
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
    
    $imported = 0;
    $updated = 0;
    
    // Períodos a sincronizar (por semanas para evitar timeout)
    $periods = [
        ['2025-09-01', '2025-09-30'],
        ['2025-10-01', '2025-10-31'], 
        ['2025-11-01', '2025-11-30'],
        ['2025-12-01', '2025-12-27']
    ];
    
    foreach ($periods as $period) {
        $url = 'https://integrations.payment.haulmer.com/Report/get-report';
        
        $data = [
            'Filters' => [
                'StartDate' => $period[0],
                'EndDate' => $period[1]
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
                'X-API-Key: ' . $config['tuu_api_key'],
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['content']['reports'])) {
                foreach ($result['content']['reports'] as $t) {
                    $checkSql = "SELECT id FROM tuu_pos_transactions WHERE sale_id = ?";
                    $checkStmt = $pdo->prepare($checkSql);
                    $checkStmt->execute([$t['saleId']]);
                    
                    if ($checkStmt->fetch()) {
                        $updated++;
                    } else {
                        $insertSql = "INSERT INTO tuu_pos_transactions 
                            (sale_id, amount, status, pos_serial_number, transaction_type, payment_date_time, items_json, extra_data_json)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $insertStmt = $pdo->prepare($insertSql);
                        $insertStmt->execute([
                            $t['saleId'],
                            $t['amount'],
                            $t['status'],
                            $t['posSerialNumber'],
                            $t['typeTransaction'],
                            $t['paymentDataTime'],
                            json_encode($t['extraData']['items'] ?? []),
                            json_encode($t['extraData'] ?? [])
                        ]);
                        $imported++;
                    }
                }
            }
        }
        
        usleep(500000); // Pausa 0.5s entre períodos
    }
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'updated' => $updated,
        'message' => "Sincronización completada: $imported nuevas transacciones"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>