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
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

// Obtener datos de crédito R11 del usuario
$stmt = $conn->prepare("
    SELECT 
        es_credito_r11,
        credito_r11_aprobado,
        limite_credito_r11,
        credito_r11_usado,
        relacion_r11,
        fecha_aprobacion_r11
    FROM usuarios 
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['es_credito_r11'] || !$user['credito_r11_aprobado']) {
    echo json_encode([
        'success' => false,
        'error' => 'Usuario no tiene crédito R11 aprobado',
        'is_r11' => $user['es_credito_r11'] ?? 0,
        'is_approved' => $user['credito_r11_aprobado'] ?? 0
    ]);
    exit;
}

// Calcular crédito disponible
$credito_disponible = $user['limite_credito_r11'] - $user['credito_r11_usado'];

// Obtener historial de transacciones (últimas 20)
$stmt_trans = $conn->prepare("
    SELECT 
        id,
        amount,
        type,
        description,
        order_id,
        created_at
    FROM r11_credit_transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");

$stmt_trans->bind_param("i", $user_id);
$stmt_trans->execute();
$trans_result = $stmt_trans->get_result();

$transactions = [];
while ($row = $trans_result->fetch_assoc()) {
    $transactions[] = $row;
}

echo json_encode([
    'success' => true,
    'credit' => [
        'limite_credito' => floatval($user['limite_credito_r11']),
        'credito_usado' => floatval($user['credito_r11_usado']),
        'credito_disponible' => floatval($credito_disponible),
        'relacion_r11' => $user['relacion_r11'],
        'fecha_aprobacion' => $user['fecha_aprobacion_r11']
    ],
    'transactions' => $transactions
]);

$conn->close();
?>
