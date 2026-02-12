<?php
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die('Config no encontrado');
}

echo "🔍 Testing Haulmer API directly...\n\n";

$url = 'https://integrations.payment.haulmer.com/Report/get-report';
$data = [
    'Filters' => [
        'StartDate' => '2025-09-01',
        'EndDate' => '2025-09-14'
    ],
    'page' => 1,
    'pageSize' => 20
];

echo "📡 API Key: " . substr($config['tuu_api_key'], 0, 10) . "...\n";
echo "📅 Date Range: 2025-09-01 to 2025-09-14\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $config['tuu_api_key'],
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($error) {
    echo "cURL Error: $error\n";
}

if ($response) {
    $result = json_decode($response, true);
    if ($result) {
        echo "✅ Response received\n";
        echo "Code: " . ($result['code'] ?? 'N/A') . "\n";
        echo "Message: " . ($result['message'] ?? 'N/A') . "\n";
        
        if (isset($result['content']['reports'])) {
            $reports = $result['content']['reports'];
            echo "📊 Reports found: " . count($reports) . "\n";
            
            if (count($reports) > 0) {
                $total = array_sum(array_column($reports, 'amount'));
                echo "💰 Total amount: $" . number_format($total) . "\n\n";
                
                echo "🔍 First 3 transactions:\n";
                for ($i = 0; $i < min(3, count($reports)); $i++) {
                    $t = $reports[$i];
                    echo "  " . ($i+1) . ". ID: {$t['saleId']} | Amount: \${$t['amount']} | POS: {$t['posSerialNumber']}\n";
                }
            }
        }
    } else {
        echo "❌ Invalid JSON response\n";
        echo "Raw response: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "❌ No response\n";
}
?>