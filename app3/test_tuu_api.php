<?php
$config = require_once __DIR__ . '/config.php';

echo "=== TEST TUU API ===\n";
echo "API Key: " . substr($config['tuu_api_key'], 0, 20) . "...\n\n";

// Obtener datos de TUU Reports
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
echo "Response:\n";
echo $response;

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['content'])) {
        echo "\n\n=== DATOS PARA MYSQL ===\n";
        foreach ($data['content'] as $i => $transaction) {
            echo "\nTransacción " . ($i+1) . ":\n";
            echo "- Sequence: " . $transaction['sequenceNumber'] . "\n";
            echo "- Amount: " . $transaction['amount'] . "\n";
            echo "- Device: " . $transaction['deviceSerial'] . "\n";
            echo "- Date: " . $transaction['transactionDate'] . "\n";
        }
    }
}
?>