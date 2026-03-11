<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://caja.laruta11.cl');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
];
$config = null;
foreach ($config_paths as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if ($conn->connect_error) { echo json_encode(['success' => false, 'error' => 'DB error']); exit; }
$conn->set_charset('utf8mb4');

$nombre    = $_POST['nombre']    ?? 'Test Usuario';
$email     = $_POST['email']     ?? 'test_rl6_' . time() . '@test.cl';
$rut       = $_POST['rut']       ?? '11.111.111-1';
$grado     = $_POST['grado']     ?? 'Cabo';
$unidad    = $_POST['unidad']    ?? 'RL6';
$domicilio = $_POST['domicilio'] ?? 'Dirección de prueba';

// Insertar usuario de prueba con email único para evitar duplicados
$unique_email = 'test_rl6_' . time() . '@test.cl';

$stmt = $conn->prepare("
    INSERT INTO usuarios (nombre, email, es_militar_rl6, rut, grado_militar, unidad_trabajo, domicilio_particular,
        selfie_url, carnet_frontal_url, carnet_trasero_url, fecha_solicitud_rl6, credito_aprobado, activo)
    VALUES (?, ?, 1, ?, ?, ?, ?, '', '', '', NOW(), 0, 1)
");
$stmt->bind_param('ssssss', $nombre, $unique_email, $rut, $grado, $unidad, $domicilio);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Error al insertar: ' . $stmt->error]);
    exit;
}
$user_id = $conn->insert_id;
$stmt->close();
$conn->close();

// Token Telegram desde caja3/config o env
$config_paths_tg = [
    __DIR__ . '/../../../caja3/config.php',
    __DIR__ . '/../../../../caja3/config.php',
];
$config_tg = null;
foreach ($config_paths_tg as $p) {
    if (file_exists($p)) { $config_tg = require $p; break; }
}
$tg_token   = ($config_tg['telegram_token']   ?? null) ?: getenv('TELEGRAM_TOKEN');
$tg_chat_id = ($config_tg['telegram_chat_id'] ?? null) ?: getenv('TELEGRAM_CHAT_ID');
$telegram_sent = false;

if ($tg_token && $tg_chat_id) {
    $esc = fn($t) => str_replace(['_','*','[','`'], ['\\_','\\*','\\[','\\`'], (string)$t);
    $msg  = "🧪 *[TEST] NUEVA SOLICITUD RL6*\n";
    $msg .= "──────────────────\n";
    $msg .= "👤 *Nombre:* " . $esc($nombre) . "\n";
    $msg .= "📧 *Email:* " . $unique_email . "\n";
    $msg .= "🪪 *RUT:* " . $rut . "\n";
    $msg .= "🎗️ *Grado:* " . $esc($grado) . "\n";
    $msg .= "🏢 *Unidad:* " . $esc($unidad) . "\n";
    $msg .= "🏠 *Domicilio:* " . $esc($domicilio) . "\n";
    $msg .= "──────────────────\n";
    $msg .= "_(Sin fotos — simulación)_\n";
    $msg .= "──────────────────\n";
    $msg .= "Aprobar credito?";

    $buttons = [
        [
            ['text' => 'Aprobar $50.000', 'callback_data' => "approve_rl6_{$user_id}_50000"],
            ['text' => 'Aprobar $30.000', 'callback_data' => "approve_rl6_{$user_id}_30000"],
        ],
        [
            ['text' => 'Aprobar $20.000', 'callback_data' => "approve_rl6_{$user_id}_20000"],
            ['text' => 'Rechazar',        'callback_data' => "reject_rl6_{$user_id}"],
        ],
    ];

    $ch = curl_init("https://api.telegram.org/bot{$tg_token}/sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'chat_id'      => $tg_chat_id,
        'text'         => $msg,
        'parse_mode'   => 'Markdown',
        'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
    ]);
    $tg_res = curl_exec($ch);
    curl_close($ch);
    $tg_data = json_decode($tg_res, true);
    $telegram_sent = !empty($tg_data['ok']);
}

echo json_encode([
    'success'       => true,
    'user_id'       => $user_id,
    'telegram_sent' => $telegram_sent,
]);
