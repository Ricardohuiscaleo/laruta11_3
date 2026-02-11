<?php
// Script para probar la sincronización de TUU
$config = require_once __DIR__ . '/config.php';

echo "=== SINCRONIZACIÓN TUU PAYMENTS ===\n";

try {
    // Conectar a MySQL
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Conexión MySQL exitosa\n";

    // Obtener datos de TUU Reports
    echo "Obteniendo reportes de TUU...\n";
    $url = 'https://integrations.payment.haulmer.com/Reports/GetTransactions';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . $config['tuu_api_key']
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    
    if ($httpCode !== 200) {
        throw new Exception("Error HTTP: $httpCode");
    }
    
    $tuuData = json_decode($response, true);
    
    if (!$tuuData || !isset($tuuData['content'])) {
        throw new Exception('Respuesta inválida de TUU');
    }
    
    echo "✓ Datos obtenidos de TUU: " . count($tuuData['content']) . " transacciones\n";
    
    // Mostrar algunas transacciones
    foreach (array_slice($tuuData['content'], 0, 3) as $i => $transaction) {
        echo "\nTransacción " . ($i+1) . ":\n";
        echo "- Sequence: " . $transaction['sequenceNumber'] . "\n";
        echo "- Amount: " . $transaction['amount'] . "\n";
        echo "- Device: " . $transaction['deviceSerial'] . "\n";
        echo "- Date: " . $transaction['transactionDate'] . "\n";
    }
    
    // Insertar datos en tuu_payments
    $inserted = 0;
    foreach ($tuuData['content'] as $transaction) {
        // Verificar si ya existe
        $checkSql = "SELECT id FROM tuu_payments WHERE order_number = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$transaction['sequenceNumber']]);
        
        if (!$checkStmt->fetch()) {
            // Insertar nueva transacción
            $insertSql = "INSERT INTO tuu_payments (
                order_number, device_serial, status, amount, 
                description, tuu_response, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $transaction['sequenceNumber'],
                $transaction['deviceSerial'],
                'completed',
                $transaction['amount'],
                'Transacción TUU importada',
                json_encode($transaction),
                $transaction['transactionDate']
            ]);
            $inserted++;
        }
    }
    
    echo "\n✓ Se insertaron $inserted nuevas transacciones en tuu_payments\n";
    
    // Mostrar total de registros
    $countSql = "SELECT COUNT(*) as total FROM tuu_payments";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    echo "✓ Total de pagos en base de datos: $total\n";
    echo "\n=== SINCRONIZACIÓN COMPLETADA ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>