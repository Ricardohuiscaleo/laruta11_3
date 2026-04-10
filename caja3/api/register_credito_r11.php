<?php
header('Content-Type: application/json');

// Verificar sesión de admin
require_once __DIR__ . '/session_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// CORS restringido
$allowed_origins = ['https://app.laruta11.cl', 'https://caja.laruta11.cl'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? null;
$relacion_r11 = $input['relacion_r11'] ?? null;
$limite_credito_r11 = floatval($input['limite_credito_r11'] ?? 0);
$auto_approve = $input['auto_approve'] ?? false;

// Datos para usuario nuevo (si no se provee user_id)
$nombre = $input['nombre'] ?? null;
$telefono = $input['telefono'] ?? null;
$email = $input['email'] ?? null;

if (!$relacion_r11) {
    echo json_encode(['success' => false, 'error' => 'relacion_r11 es requerido']);
    exit;
}

try {
    $conn = new mysqli(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );

    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();

    if ($user_id) {
        // Usuario existente: actualizar como beneficiario R11
        $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new Exception('Usuario no encontrado');
        }

        $sql = "UPDATE usuarios SET es_credito_r11 = 1, relacion_r11 = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $relacion_r11, $user_id);
        $stmt->execute();
        $stmt->close();

    } else {
        // Usuario nuevo: crear con datos básicos
        if (!$nombre) {
            throw new Exception('nombre es requerido para usuario nuevo');
        }

        $sql = "INSERT INTO usuarios (nombre, telefono, email, es_credito_r11, relacion_r11, fecha_registro) 
                VALUES (?, ?, ?, 1, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $nombre, $telefono, $email, $relacion_r11);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
    }

    // Auto-aprobar si se solicita
    if ($auto_approve && $limite_credito_r11 > 0) {
        $sql = "UPDATE usuarios SET 
                    credito_r11_aprobado = 1, 
                    limite_credito_r11 = ?, 
                    fecha_aprobacion_r11 = NOW() 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('di', $limite_credito_r11, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Beneficiario R11 registrado exitosamente',
        'user_id' => $user_id,
        'auto_approved' => $auto_approve && $limite_credito_r11 > 0
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
