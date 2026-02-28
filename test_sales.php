<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_paths = [
    __DIR__ . '/caja3/public/config.php',
    __DIR__ . '/caja3/config.php'
];

$config = null;
foreach ($config_paths as $p) {
    if (file_exists($p)) {
        $config = require_once $p;
        break;
    }
}

if (!$config) {
    die("No config");
}

$pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$mes = '2026-02';
$year = explode('-', $mes)[0];
$month = explode('-', $mes)[1];

$sql = "SELECT installment_amount as amount, tuu_amount as tuu_amount, product_price as product_price, delivery_fee, created_at, payment_status, order_number FROM tuu_orders WHERE payment_status = 'paid' AND order_number NOT LIKE 'RL6-%'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ventasDashboard = 0;
$ventasMine = 0;

foreach ($transactions as $transaction) {
    $amountDashboard = (float)($transaction['amount'] ?? 0);
    $amountMine = (float)($transaction['amount'] ?? $transaction['tuu_amount'] ?? $transaction['product_price'] ?? 0);

    $deliveryFee = (float)($transaction['delivery_fee'] ?? 0);

    $netAmountDashboard = $amountDashboard - $deliveryFee;
    $netAmountMine = $amountMine - $deliveryFee;

    $transDate = new DateTime($transaction['created_at'], new DateTimeZone('UTC'));
    $transDate->setTimezone(new DateTimeZone('America/Santiago'));
    $hour = (int)$transDate->format('G');

    $shiftDate = clone $transDate;
    if ($hour >= 0 && $hour < 4) {
        $shiftDate->modify('-1 day');
    }

    if ($shiftDate->format('Y-m') === "$year-$month") {
        if ($amountDashboard > 0)
            $ventasDashboard += $netAmountDashboard;
        if ($amountMine > 0)
            $ventasMine += $netAmountMine;
    }
}
echo "Dashboard Math: $ventasDashboard\n";
echo "My PHP Math: $ventasMine\n";
?>