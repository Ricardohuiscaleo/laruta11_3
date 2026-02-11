<?php
header('Content-Type: text/plain');

echo "🔄 Sincronizando transacciones históricas...\n";

// Usar credenciales directas
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
        'u958525313_app',
        'wEzho0-hujzoz-cevzin'
    );
    echo "✅ Conexión DB exitosa\n";
} catch (Exception $e) {
    echo "❌ Error DB: " . $e->getMessage() . "\n";
    exit;
}

// Obtener API key
$config = null;
for ($i = 1; $i <= 5; $i++) {
    $configPath = str_repeat('../', $i) . 'config.php';
    if (file_exists(__DIR__ . '/' . $configPath)) {
        $config = require_once __DIR__ . '/' . $configPath;
        break;
    }
}

if (!$config || !isset($config['tuu_api_key'])) {
    echo "❌ API Key no encontrada\n";
    exit;
}

// Fechas a sincronizar (últimos 7 días)
$dates = [];
for ($i = 0; $i < 7; $i++) {
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}

echo "📅 Sincronizando fechas: " . implode(', ', $dates) . "\n\n";

$totalProcessed = 0;

foreach ($dates as $date) {
    echo "🔍 Procesando $date...\n";
    
    $ch = curl_init('https://integrations.payment.haulmer.com/Report/get-report');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $config['tuu_api_key'],
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
    curl_close($ch);
    
    if ($response) {
        $result = json_decode($response, true);
        if ($result && isset($result['content']['reports'])) {
            $count = 0;
            foreach ($result['content']['reports'] as $t) {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO tuu_pos_transactions 
                    (sale_id, amount, status, pos_serial_number, transaction_type, payment_date_time, items_json, extra_data_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $inserted = $stmt->execute([
                    $t['saleId'],
                    $t['amount'],
                    $t['status'],
                    $t['posSerialNumber'],
                    $t['typeTransaction'],
                    $t['paymentDataTime'],
                    json_encode($t['extraData']['items'] ?? []),
                    json_encode($t['extraData'] ?? [])
                ]);
                if ($inserted && $stmt->rowCount() > 0) {
                    $count++;
                }
            }
            echo "   ✅ $count nuevas transacciones guardadas\n";
            $totalProcessed += $count;
        } else {
            echo "   ⚠️ Sin transacciones\n";
        }
    } else {
        echo "   ❌ Error en API\n";
    }
}

echo "\n🎉 Sincronización completa: $totalProcessed transacciones procesadas\n";

// Mostrar resumen
$stmt = $pdo->prepare("SELECT DATE(payment_date_time) as date, COUNT(*) as count, SUM(amount) as total FROM tuu_pos_transactions GROUP BY DATE(payment_date_time) ORDER BY date DESC LIMIT 10");
$stmt->execute();
$summary = $stmt->fetchAll();

echo "\n📊 Resumen por día:\n";
foreach ($summary as $row) {
    echo "   {$row['date']}: {$row['count']} transacciones - $" . number_format($row['total']) . "\n";
}
?>