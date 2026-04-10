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

    $usuario_id = $_POST['usuario_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $limite_credito = $_POST['limite_credito'] ?? 0;

    if (!$usuario_id || !$action) {
        throw new Exception('Faltan parámetros requeridos');
    }

    if ($action === 'approve') {
        $sql = "UPDATE usuarios SET 
                    credito_r11_aprobado = 1,
                    limite_credito_r11 = ?,
                    fecha_aprobacion_r11 = NOW()
                WHERE id = ? AND es_credito_r11 = 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('di', $limite_credito, $usuario_id);

    } elseif ($action === 'reject') {
        $sql = "UPDATE usuarios SET 
                    credito_r11_aprobado = 0,
                    limite_credito_r11 = 0
                WHERE id = ? AND es_credito_r11 = 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $usuario_id);

    } else {
        throw new Exception('Acción inválida');
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $action === 'approve' ? 'Crédito R11 aprobado exitosamente' : 'Crédito R11 rechazado'
        ]);
    } else {
        throw new Exception('Error al actualizar: ' . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
