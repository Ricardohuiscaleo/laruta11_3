<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
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
    die(json_encode(['success' => false, 'error' => 'Configuración no encontrada']));
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión']));
}

$conn->set_charset('utf8mb4');

// Auto-migración selectiva: asegurar que las columnas necesarias existen
$check_cols = [
    'rut' => "VARCHAR(12) NULL",
    'grado_militar' => "VARCHAR(100) NULL",
    'unidad_trabajo' => "VARCHAR(255) NULL",
    'domicilio_particular' => "TEXT NULL",
    'carnet_frontal_url' => "VARCHAR(500) NULL",
    'carnet_trasero_url' => "VARCHAR(500) NULL",
    'selfie_url' => "VARCHAR(500) NULL",
    'es_militar_rl6' => "TINYINT(1) DEFAULT 0",
    'credito_aprobado' => "TINYINT(1) DEFAULT 0",
    'limite_credito' => "DECIMAL(10,2) DEFAULT 0.00",
    'credito_usado' => "DECIMAL(10,2) DEFAULT 0.00",
    'fecha_solicitud_rl6' => "TIMESTAMP NULL",
    'fecha_aprobacion_rl6' => "TIMESTAMP NULL"
];

$res = $conn->query("SHOW COLUMNS FROM usuarios");
$existing_cols = [];
if ($res) {
    while ($row = $res->fetch_assoc())
        $existing_cols[] = $row['Field'];
}

foreach ($check_cols as $col => $definition) {
    if (!in_array($col, $existing_cols)) {
        $conn->query("ALTER TABLE usuarios ADD COLUMN $col $definition");
    }
}

// Rate Limiting: 11 intentos por IP en 11 minutos
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = sys_get_temp_dir() . '/rl6_rate_' . md5($ip) . '.txt';
$current_time = time();
$unlock_code = $_POST['unlock_code'] ?? null;

// Verificar código de desbloqueo
if ($unlock_code && isset($config['unlock_code']) && $unlock_code === $config['unlock_code']) {
    // Eliminar archivo de rate limit si el código es correcto
    if (file_exists($rate_limit_file)) {
        unlink($rate_limit_file);
    }
}
else {
    // Aplicar rate limiting normal
    if (file_exists($rate_limit_file)) {
        $data = json_decode(file_get_contents($rate_limit_file), true);
        $attempts = $data['attempts'] ?? [];
        $blocked_until = $data['blocked_until'] ?? 0;

        // Verificar si está bloqueado
        if ($blocked_until > $current_time) {
            $remaining = $blocked_until - $current_time;
            $minutes = ceil($remaining / 60);
            echo json_encode([
                'success' => false,
                'error' => 'Demasiados intentos. Intenta en ' . $minutes . ' minuto' . ($minutes > 1 ? 's' : '') . '.',
                'blocked_until' => $blocked_until,
                'remaining_seconds' => $remaining
            ]);
            exit;
        }

        // Filtrar intentos de los últimos 11 minutos
        $attempts = array_filter($attempts, function ($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 660; // 11 minutos
        });

        if (count($attempts) >= 11) {
            // Bloquear por 11 minutos
            $blocked_until = $current_time + 660;
            file_put_contents($rate_limit_file, json_encode([
                'attempts' => $attempts,
                'blocked_until' => $blocked_until
            ]));
            echo json_encode([
                'success' => false,
                'error' => 'Demasiados intentos. Intenta en 11 minutos.',
                'blocked_until' => $blocked_until,
                'remaining_seconds' => 660
            ]);
            exit;
        }
        $attempts[] = $current_time;
    }
    else {
        $attempts = [$current_time];
    }
    file_put_contents($rate_limit_file, json_encode(['attempts' => $attempts, 'blocked_until' => 0]));
}

// Validar datos requeridos
$user_id = $_POST['user_id'] ?? null;
$rut = $_POST['rut'] ?? null;
$grado_militar = $_POST['grado_militar'] ?? null;
$unidad_trabajo = $_POST['unidad_trabajo'] ?? null;
$domicilio_particular = $_POST['domicilio_particular'] ?? null;

if (!$user_id || !$rut || !$grado_militar || !$unidad_trabajo || !$domicilio_particular) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
    exit;
}

// Validar imágenes
if (!isset($_FILES['selfie']) || !isset($_FILES['carnet_frontal']) || !isset($_FILES['carnet_trasero'])) {
    echo json_encode(['success' => false, 'error' => 'Faltan imágenes requeridas']);
    exit;
}

// Subir imágenes a AWS S3
require_once __DIR__ . '/../S3Manager.php';
function uploadToS3($file, $type, $user_id, $config)
{
    try {
        $s3 = new S3Manager($config);
        $fileName = 'carnets-militares/' . $user_id . '_' . $type . '_' . time() . '_' . basename($file['name']);
        return $s3->uploadFile($file, $fileName);
    } catch (Throwable $e) {
        error_log('uploadToS3 error: ' . $e->getMessage());
        return null;
    }
}

$selfie_url = uploadToS3($_FILES['selfie'], 'selfie', $user_id, $config);
$carnet_frontal_url = uploadToS3($_FILES['carnet_frontal'], 'frontal', $user_id, $config);
$carnet_trasero_url = uploadToS3($_FILES['carnet_trasero'], 'trasero', $user_id, $config);

if (!$selfie_url || !$carnet_frontal_url || !$carnet_trasero_url) {
    echo json_encode(['success' => false, 'error' => 'Error al subir imágenes']);
    exit;
}

// Actualizar usuario en BD
try {
    // Generar google_id único si no existe
    $google_id = 'rl6_' . $user_id . '_' . time();

    $stmt = $conn->prepare("
        UPDATE usuarios SET 
            es_militar_rl6 = 1,
            google_id = IF(google_id IS NULL OR google_id = '', ?, google_id),
            rut = ?,
            grado_militar = ?,
            unidad_trabajo = ?,
            domicilio_particular = ?,
            selfie_url = ?,
            carnet_frontal_url = ?,
            carnet_trasero_url = ?,
            fecha_solicitud_rl6 = NOW(),
            credito_aprobado = 0,
            limite_credito = 0,
            credito_usado = 0
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssssssi",
        $google_id,
        $rut,
        $grado_militar,
        $unidad_trabajo,
        $domicilio_particular,
        $selfie_url,
        $carnet_frontal_url,
        $carnet_trasero_url,
        $user_id
    );

    if ($stmt->execute()) {
        // Obtener email del usuario
        $stmt_user = $conn->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();
        $user_data = $user_result->fetch_assoc();

        // Enviar email de confirmación
        if ($user_data && $user_data['email']) {
            @include_once __DIR__ . '/send_email.php';
            @sendRL6Email(
                $user_data['email'],
                $user_data['nombre'],
                $rut,
                $grado_militar,
                $unidad_trabajo,
                'registro'
            );
        }

        // Notificar por Telegram para aprobación
        $config_paths_tg = [
            __DIR__ . '/../../../caja3/config.php',
            __DIR__ . '/../../../../caja3/config.php',
        ];
        $config_tg = null;
        foreach ($config_paths_tg as $p) {
            if (file_exists($p)) { $config_tg = require $p; break; }
        }
        $tg_token   = ($config_tg['telegram_token'] ?? null) ?: getenv('TELEGRAM_TOKEN');
        $tg_chat_id = ($config_tg['telegram_chat_id'] ?? null) ?: getenv('TELEGRAM_CHAT_ID');
        if ($tg_token && $tg_chat_id) {
                $esc = fn($t) => str_replace(['_','*','[','`'], ['\\_','\\*','\\[','\\`'], $t);
                $nombre_tg = $user_data['nombre'] ?? 'Sin nombre';
                $email_tg  = $user_data['email'] ?? '';
                $msg  = "\xF0\x9F\x8E\x96\xEF\xB8\x8F *NUEVA SOLICITUD RL6*\n";
                $msg .= "\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n";
                $msg .= "\xF0\x9F\x91\xA4 *Nombre:* " . $esc($nombre_tg) . "\n";
                $msg .= "\xF0\x9F\x93\xA7 *Email:* " . $email_tg . "\n";
                $msg .= "\xF0\x9F\xAA\xAA *RUT:* " . $rut . "\n";
                $msg .= "\xF0\x9F\x8E\x97\xEF\xB8\x8F *Grado:* " . $esc($grado_militar) . "\n";
                $msg .= "\xF0\x9F\x8F\xA2 *Unidad:* " . $esc($unidad_trabajo) . "\n";
                $msg .= "\xF0\x9F\x8F\xA0 *Domicilio:* " . $esc($domicilio_particular) . "\n";
                $msg .= "\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n";
                $msg .= "Selfie: " . $selfie_url . "\n";
                $msg .= "Carnet frontal: " . $carnet_frontal_url . "\n";
                $msg .= "Carnet trasero: " . $carnet_trasero_url . "\n";
                $msg .= "\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n";
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
                curl_exec($ch);
                curl_close($ch);
            }

        echo json_encode([
            'success' => true,
            'message' => 'Solicitud enviada exitosamente. Te contactaremos en 24 horas.',
            'data' => [
                'user_id' => $user_id,
                'rut' => $rut,
                'grado_militar' => $grado_militar,
                'unidad_trabajo' => $unidad_trabajo,
                'selfie_url' => $selfie_url,
                'carnet_frontal_url' => $carnet_frontal_url,
                'carnet_trasero_url' => $carnet_trasero_url
            ]
        ]);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar datos: ' . $stmt->error]);
    }

    $stmt->close();
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>