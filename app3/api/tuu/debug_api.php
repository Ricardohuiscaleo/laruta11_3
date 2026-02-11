<?php
header('Content-Type: text/plain');

echo "🔍 Debug API Haulmer - 21 de septiembre\n";
echo "=====================================\n\n";

// API Key directa
$apiKey = 'keWNyXzoj37YvSMi33RPrbppIdTAzqBmpxEJ6yEPnT9UjRfxQQ9CzlcJYPn45aNEw1sXc63Vv32t93Et4KYhQFCbaM3RpA2BOHzwq383mvHDp5YY314x4N7N0XSrz3';

// Probar diferentes formatos de fecha para el 21 de septiembre
$dateFormats = [
    '2025-09-21',
    '21-09-2025', 
    '2025/09/21',
    '21/09/2025'
];

foreach ($dateFormats as $date) {
    echo "🧪 Probando formato: $date\n";
    
    $ch = curl_init('https://integrations.payment.haulmer.com/Report/get-report');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'Filters' => [
            'StartDate' => $date,
            'EndDate' => $date
        ],
        'page' => 1,
        'pageSize' => 20
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   HTTP Code: $httpCode\n";
    
    if ($response) {
        $result = json_decode($response, true);
        if ($result && isset($result['content']['reports'])) {
            $count = count($result['content']['reports']);
            echo "   ✅ $count transacciones encontradas\n";
            
            if ($count > 0) {
                echo "   📋 Primeras transacciones:\n";
                foreach (array_slice($result['content']['reports'], 0, 3) as $t) {
                    echo "      - ID: {$t['saleId']} | Monto: {$t['amount']} | Fecha: {$t['paymentDataTime']}\n";
                }
                break; // Encontramos el formato correcto
            }
        } else {
            echo "   ❌ Sin transacciones o error en respuesta\n";
            if ($result) {
                echo "   📄 Respuesta: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
            }
        }
    } else {
        echo "   ❌ Error en cURL\n";
    }
    echo "\n";
}
?>