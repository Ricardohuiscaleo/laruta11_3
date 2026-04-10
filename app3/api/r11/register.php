<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

// CORS restringido
$allowed_origins = ['https://app.laruta11.cl', 'https://caja.laruta11.cl'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

// Rate Limiting con Redis: 5 intentos por IP en 10 minutos
$ip = $_SERVER['REMOTE_ADDR'];

function checkRateLimit($ip, $max_attempts = 5, $window_seconds = 600) {
    try {
        $redis = new Redis();
        $redis_host = getenv('REDIS_HOST') ?: 'coolify-redis';
        $redis_port = getenv('REDIS_PORT') ?: 6379;
        $redis->connect($redis_host, (int)$redis_port);
        $redis_pass = getenv('REDIS_PASSWORD');
        if ($redis_pass) $redis->auth($redis_pass);

        $key = "r11_rate:{$ip}";
        $attempts = $redis->incr($key);
        if ($attempts === 1) $redis->expire($key, $window_seconds);

        return $attempts <= $max_attempts;
    } catch (Exception $e) {
        // Si Redis no está disponible, permitir el request (fail-open)
        error_log('Redis rate limit error: ' . $e->getMessage());
        return true;
    }
}

if (!checkRateLimit($ip)) {
    echo json_encode([
        'success' => false,
        'error' => 'Demasiados intentos. Intenta en 10 minutos.'
    ]);
    exit;
}

// Validar datos requeridos
$user_id = $_POST['user_id'] ?? null;
$rut = $_POST['rut'] ?? null;
$rol = $_POST['rol'] ?? null;

if (!$user_id || !$rut || !$rol) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos (user_id, rut, rol)']);
    exit;
}

// SEGURIDAD: Validar session_token y que user_id coincida
$session_token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE['session_token'] ?? null;
if (!$session_token) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE session_token = ? AND activo = 1");
$stmt->bind_param("s", $session_token);
$stmt->execute();
$auth_user = $stmt->get_result()->fetch_assoc();

if (!$auth_user || $auth_user['id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Validar que el usuario existe
$stmt = $conn->prepare("SELECT id, nombre, email, telefono FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

// Validar formato RUT chileno (ej: 17638433-6, 12345678-K)
function validarRut($rut) {
    $rut = strtoupper(trim($rut));
    if (!preg_match('/^\d{7,8}-[\dK]$/', $rut)) {
        return false;
    }
    $parts = explode('-', $rut);
    $numero = $parts[0];
    $dv_ingresado = $parts[1];

    $suma = 0;
    $multiplicador = 2;
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += intval($numero[$i]) * $multiplicador;
        $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
    }
    $resto = $suma % 11;
    $dv_calculado = 11 - $resto;

    if ($dv_calculado === 11) $dv_calculado = '0';
    elseif ($dv_calculado === 10) $dv_calculado = 'K';
    else $dv_calculado = (string)$dv_calculado;

    return $dv_ingresado === $dv_calculado;
}

if (!validarRut($rut)) {
    echo json_encode(['success' => false, 'error' => 'Formato de RUT inválido']);
    exit;
}

// Validar rol
$roles_validos = ['Planchero/a', 'Cajero/a', 'Rider', 'Otro'];
if (!in_array($rol, $roles_validos)) {
    echo json_encode(['success' => false, 'error' => 'Rol inválido. Opciones: ' . implode(', ', $roles_validos)]);
    exit;
}

// Validar selfie
if (!isset($_FILES['selfie'])) {
    echo json_encode(['success' => false, 'error' => 'Falta imagen de selfie']);
    exit;
}

// Subir selfie a AWS S3
require_once __DIR__ . '/../S3Manager.php';

function uploadToS3($file, $type, $user_id, $config) {
    try {
        $s3 = new S3Manager($config);
        $fileName = 'carnets-trabajadores/' . $user_id . '_' . $type . '_' . time() . '_' . basename($file['name']);
        return $s3->uploadFile($file, $fileName);
    } catch (Throwable $e) {
        error_log('uploadToS3 error: ' . $e->getMessage());
        return null;
    }
}

$selfie_url = uploadToS3($_FILES['selfie'], 'selfie', $user_id, $config);

if (!$selfie_url) {
    echo json_encode(['success' => false, 'error' => 'Error al subir selfie']);
    exit;
}

// Procesar datos del QR del carnet
$carnet_qr_data = $_POST['carnet_qr_data'] ?? null;
$carnet_qr_json = null;

if ($carnet_qr_data) {
    $qr_parsed = json_decode($carnet_qr_data, true);
    if ($qr_parsed && isset($qr_parsed['rut']) && isset($qr_parsed['serial']) && isset($qr_parsed['mrz'])) {
        // Validar contra el portal del registro civil
        $validation_result = validateCarnetWithRegistroCivil($qr_parsed);
        $qr_parsed['validated'] = $validation_result['valid'];
        $qr_parsed['validation_status'] = $validation_result['status'] ?? 'unknown';
        $qr_parsed['validated_at'] = date('Y-m-d H:i:s');
        $carnet_qr_json = json_encode($qr_parsed, JSON_UNESCAPED_UNICODE);
    }
}

// Actualizar usuario en BD
try {
    $stmt = $conn->prepare("
        UPDATE usuarios SET 
            es_credito_r11 = 1,
            rut = ?,
            selfie_url = ?,
            relacion_r11 = ?,
            carnet_qr_data = ?,
            credito_r11_aprobado = 0,
            limite_credito_r11 = 0,
            credito_r11_usado = 0
        WHERE id = ?
    ");
    $stmt->bind_param("ssssi", $rut, $selfie_url, $rol, $carnet_qr_json, $user_id);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar datos: ' . $stmt->error]);
        exit;
    }

    // INSERT/UPDATE en tabla personal para vincular con mi3
    $rol_map = [
        'Planchero/a' => 'planchero',
        'Cajero/a' => 'cajero',
        'Rider' => 'rider',
        'Otro' => 'cajero'
    ];
    $personal_rol = $rol_map[$rol] ?? 'cajero';

    // Verificar si ya existe en personal
    $stmt_check = $conn->prepare("SELECT id FROM personal WHERE user_id = ?");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $existing_personal = $stmt_check->get_result()->fetch_assoc();

    if ($existing_personal) {
        $stmt_personal = $conn->prepare("
            UPDATE personal SET 
                nombre = ?, telefono = ?, email = ?, rut = ?, rol = ?
            WHERE user_id = ?
        ");
        $stmt_personal->bind_param("sssssi",
            $user_data['nombre'],
            $user_data['telefono'],
            $user_data['email'],
            $rut,
            $personal_rol,
            $user_id
        );
    } else {
        $stmt_personal = $conn->prepare("
            INSERT INTO personal (nombre, telefono, email, rut, rol, user_id, activo)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt_personal->bind_param("sssssi",
            $user_data['nombre'],
            $user_data['telefono'],
            $user_data['email'],
            $rut,
            $personal_rol,
            $user_id
        );
    }
    $stmt_personal->execute();

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
        $nombre_tg = $user_data['nombre'] ?? 'Sin nombre';
        $email_tg  = $user_data['email'] ?? '';
        $telefono_tg = $user_data['telefono'] ?? '';

        $msg  = "👷 NUEVA SOLICITUD CRÉDITO R11\n";
        $msg .= "──────────\n";
        $msg .= "👤 Nombre: " . $nombre_tg . "\n";
        $msg .= "📧 Email: " . $email_tg . "\n";
        $msg .= "📱 Teléfono: " . $telefono_tg . "\n";
        $msg .= "🪦 RUT: " . $rut . "\n";
        $msg .= "🏷️ Rol: " . $rol . "\n";
        if ($carnet_qr_json) {
            $qr_data = json_decode($carnet_qr_json, true);
            $msg .= "📋 Serial: " . ($qr_data['serial'] ?? '?') . "\n";
            $msg .= "🔐 Validado: " . ($qr_data['validated'] ? '✅ Sí' : '❌ No') . "\n";
        }
        $msg .= "──────────\n";
        $msg .= "Aprobar crédito?";

        $buttons = [
            [
                ['text' => '🤳 Selfie', 'url' => $selfie_url],
            ],
            [
                ['text' => 'Aprobar $50.000', 'callback_data' => "approve_r11_{$user_id}_50000"],
                ['text' => 'Aprobar $30.000', 'callback_data' => "approve_r11_{$user_id}_30000"],
            ],
            [
                ['text' => 'Aprobar $20.000', 'callback_data' => "approve_r11_{$user_id}_20000"],
                ['text' => '❌ Rechazar',    'callback_data' => "reject_r11_{$user_id}"],
            ],
        ];

        $ch = curl_init("https://api.telegram.org/bot{$tg_token}/sendMessage");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id'      => $tg_chat_id,
            'text'         => $msg,
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Solicitud enviada. Te contactaremos en 24 horas.',
        'data' => [
            'user_id' => $user_id,
            'rut' => $rut,
            'selfie_url' => $selfie_url,
            'rol' => $rol
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}

$conn->close();

/**
 * Validate carnet QR data against the Registro Civil portal.
 * Checks if the document exists and is valid by querying the SIDIV portal.
 */
function validateCarnetWithRegistroCivil(array $qrData): array {
    $run = $qrData['rut'] ?? null;
    $serial = $qrData['serial'] ?? null;
    $type = $qrData['type'] ?? 'CEDULA';
    
    if (!$run || !$serial) {
        return ['valid' => false, 'status' => 'missing_data'];
    }
    
    try {
        // Query the SIDIV portal to check document status
        $url = 'https://portal.sidiv.registrocivil.cl/usuarios-portal/pages/DocumentRequestStatus.xhtml';
        
        // First GET to obtain ViewState
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Chrome/91.0.4472.120 Mobile Safari/537.36'
        ]);
        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/rc_cookies_' . md5($run));
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/rc_cookies_' . md5($run));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return ['valid' => false, 'status' => 'portal_unreachable'];
        }
        
        // Extract ViewState
        preg_match('/name="javax\.faces\.ViewState"\s+value="([^"]+)"/', $response, $matches);
        $viewState = $matches[1] ?? null;
        
        if (!$viewState) {
            // Portal might have changed — mark as unverified but don't block
            return ['valid' => false, 'status' => 'viewstate_not_found'];
        }
        
        // POST to validate
        $postData = http_build_query([
            'form' => 'form',
            'form:run' => $run,
            'form:selectDocType' => $type,
            'form:docNumber' => $serial,
            'form:buttonHidden' => '',
            'javax.faces.ViewState' => $viewState
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Chrome/91.0.4472.120 Mobile Safari/537.36'
        ]);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/rc_cookies_' . md5($run));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Clean up cookie file
        @unlink('/tmp/rc_cookies_' . md5($run));
        
        if ($httpCode !== 200 || !$response) {
            return ['valid' => false, 'status' => 'validation_failed'];
        }
        
        // Check if the response contains a valid document status
        // The portal shows a table with class "tableResult" when the document is found
        if (strpos($response, 'tableResult') !== false || strpos($response, 'VIGENTE') !== false) {
            return ['valid' => true, 'status' => 'vigente'];
        }
        
        if (strpos($response, 'NO VIGENTE') !== false || strpos($response, 'ANULADO') !== false) {
            return ['valid' => false, 'status' => 'no_vigente'];
        }
        
        // Document not found or other status
        return ['valid' => false, 'status' => 'not_found'];
        
    } catch (Exception $e) {
        error_log('R11 carnet validation error: ' . $e->getMessage());
        return ['valid' => false, 'status' => 'error'];
    }
}
?>
