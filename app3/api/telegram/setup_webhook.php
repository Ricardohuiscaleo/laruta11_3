<?php
// Set the Telegram webhook URL for comprobante payment notifications.
// Usage: php setup_webhook.php
// Or visit this page in a browser after deploying to production.

$config_paths = [
    __DIR__ . '/../../caja3/config.php',
    __DIR__ . '/../../../caja3/config.php',
    __DIR__ . '/../../../../caja3/config.php',
];
$config = null;
foreach ($config_paths as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}

$token = ($config['telegram_token'] ?? null) ?: getenv('TELEGRAM_TOKEN');

if (!$token) {
    die("ERROR: TELEGRAM_TOKEN not found. Set it in caja3/config.php or as env var.\n");
}

$webhook_url = 'https://app.laruta11.cl/api/telegram/webhook.php';

$ch = curl_init("https://api.telegram.org/bot{$token}/setWebhook");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_POSTFIELDS => json_encode([
        'url' => $webhook_url,
        'allowed_updates' => ['callback_query'],
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$http}\n";
echo $res . "\n";

if ($http === 200) {
    $data = json_decode($res, true);
    if ($data['ok'] ?? false) {
        echo "✅ Webhook set to: {$webhook_url}\n";
        echo "   Description: {$data['description']}\n";
    } else {
        echo "❌ Telegram error: {$data['description']}\n";
    }
} else {
    echo "❌ HTTP error\n";
}
