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
header('Access-Control-Allow-Methods: GET');

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

    $status = $_GET['status'] ?? 'pending';

    $sql = "SELECT 
                id,
                nombre as name,
                email,
                telefono as phone,
                relacion_r11,
                credito_r11_aprobado,
                limite_credito_r11,
                credito_r11_usado,
                (limite_credito_r11 - credito_r11_usado) as credito_disponible,
                fecha_aprobacion_r11 as fecha_aprobacion_credito,
                fecha_ultimo_pago_r11,
                credito_r11_bloqueado,
                fecha_registro as registration_date,
                ultimo_acceso as last_login
            FROM usuarios 
            WHERE es_credito_r11 = 1";

    if ($status === 'pending') {
        $sql .= " AND credito_r11_aprobado = 0";
    } elseif ($status === 'approved') {
        $sql .= " AND credito_r11_aprobado = 1";
    }

    $sql .= " ORDER BY fecha_aprobacion_r11 DESC, id DESC";

    $result = $conn->query($sql);
    $beneficiarios = [];

    while ($row = $result->fetch_assoc()) {
        $beneficiarios[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $beneficiarios,
        'count' => count($beneficiarios)
    ]);

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
