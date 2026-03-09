<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config = null;
foreach ([__DIR__.'/../../config.php', __DIR__.'/../../../config.php', __DIR__.'/../../../../config.php'] as $p) {
    if (file_exists($p)) { $config = require_once $p; break; }
}

$pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);

$input      = json_decode(file_get_contents('php://input'), true);
$product_id = (int)($input['product_id'] ?? 0);
$new_name   = trim($input['name'] ?? '');
$new_desc   = trim($input['description'] ?? '');
$cashier    = trim($input['cashier'] ?? 'Cajera');

if (!$product_id || !$new_name) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit;
}

$stmt = $pdo->prepare("SELECT name, description FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$current) {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']); exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS product_edit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    old_name VARCHAR(255), old_description TEXT,
    new_name VARCHAR(255), new_description TEXT,
    cashier VARCHAR(100),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    telegram_message_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$ins = $pdo->prepare("INSERT INTO product_edit_requests (product_id, old_name, old_description, new_name, new_description, cashier) VALUES (?,?,?,?,?,?)");
$ins->execute([$product_id, $current['name'], $current['description'], $new_name, $new_desc, $cashier]);
$request_id = $pdo->lastInsertId();

$token  = $config['telegram_token'] ?? getenv('TELEGRAM_TOKEN');
$chatId = $config['telegram_chat_id'] ?? getenv('TELEGRAM_CHAT_ID');

$msg  = "✏️ *Solicitud de edición de producto*\n";
$msg .= "👤 Cajera: *{$cashier}*\n\n";
$msg .= "📦 Producto ID: {$product_id}\n";
$msg .= "━━━━━━━━━━━━━━━━━━━━\n";
$msg .= "*Nombre:*\n  Antes: `{$current['name']}`\n  Después: `{$new_name}`\n\n";
$msg .= "*Descripción:*\n  Antes: `".($current['description'] ?: '(vacía)'). "`\n  Después: `".($new_desc ?: '(vacía)'). "`\n";
$msg .= "━━━━━━━━━━━━━━━━━━━━\n¿Apruebas este cambio?";

$buttons = [[
    ['text' => '✅ Aprobar', 'callback_data' => "approve_edit_{$request_id}"],
    ['text' => '❌ Rechazar', 'callback_data' => "reject_edit_{$request_id}"]
]];

$ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown',
    'reply_markup' => json_encode(['inline_keyboard' => $buttons])
]);
$resp = json_decode(curl_exec($ch), true);

if (!empty($resp['result']['message_id'])) {
    $pdo->prepare("UPDATE product_edit_requests SET telegram_message_id = ? WHERE id = ?")
        ->execute([$resp['result']['message_id'], $request_id]);
}

echo json_encode(['success' => true, 'request_id' => $request_id]);
