<?php
header('Content-Type: application/json');

// Test directo del API combinado
$url = "http://" . $_SERVER['HTTP_HOST'] . "/api/tuu/get_combined_reports.php";

echo "🔍 Testing Combined API: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: " . substr($response, 0, 1000) . "\n\n";

if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "✅ API Response Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        
        if ($data['success'] && isset($data['data'])) {
            $stats = $data['data']['combined_stats'] ?? [];
            echo "📊 Combined Stats:\n";
            echo "- POS Revenue: $" . number_format($stats['pos_revenue'] ?? 0) . "\n";
            echo "- Online Revenue: $" . number_format($stats['online_revenue'] ?? 0) . "\n";
            echo "- Total Revenue: $" . number_format($stats['total_revenue'] ?? 0) . "\n";
            echo "- POS Transactions: " . ($stats['pos_transactions'] ?? 0) . "\n";
            echo "- Online Transactions: " . ($stats['online_transactions'] ?? 0) . "\n";
            echo "- Total Transactions: " . ($stats['total_transactions'] ?? 0) . "\n";
            
            $all_transactions = $data['data']['all_transactions'] ?? [];
            echo "\n📋 Total Combined Transactions: " . count($all_transactions) . "\n";
            
            if (count($all_transactions) > 0) {
                echo "\n🔍 First 3 transactions:\n";
                for ($i = 0; $i < min(3, count($all_transactions)); $i++) {
                    $t = $all_transactions[$i];
                    $source = $t['payment_source'] ?? 'unknown';
                    $amount = $t['amount'] ?? $t['totalAmount'] ?? 0;
                    $id = $t['saleId'] ?? $t['order_reference'] ?? 'N/A';
                    echo "  $i. ID: $id | Source: $source | Amount: $$amount\n";
                }
            }
        }
    } else {
        echo "❌ API Error: " . ($data['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "❌ No response from API\n";
}
?>