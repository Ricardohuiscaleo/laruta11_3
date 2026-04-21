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

$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? null;
$amount = $input['amount'] ?? null;
$order_id = $input['order_id'] ?? null;

if (!$user_id || !$amount || !$order_id) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// SEGURIDAD: Validar que amount sea numérico y positivo
$amount = filter_var($amount, FILTER_VALIDATE_FLOAT);
if ($amount === false || $amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Monto inválido']);
    exit;
}

// Verificar que es beneficiario R11 aprobado y no bloqueado
$stmt = $conn->prepare("
    SELECT 
        es_credito_r11,
        credito_r11_aprobado,
        credito_r11_bloqueado,
        limite_credito_r11,
        credito_r11_usado
    FROM usuarios 
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['es_credito_r11'] || !$user['credito_r11_aprobado']) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autorizado para usar crédito R11']);
    exit;
}

// Validar que el crédito no esté bloqueado
if (!empty($user['credito_r11_bloqueado'])) {
    echo json_encode(['success' => false, 'error' => 'Tu crédito está bloqueado por falta de pago. Por favor paga tu saldo pendiente.']);
    exit;
}

// Validar saldo disponible
$credito_disponible = $user['limite_credito_r11'] - $user['credito_r11_usado'];

if ($amount > $credito_disponible) {
    echo json_encode([
        'success' => false,
        'error' => 'Crédito insuficiente',
        'credito_disponible' => floatval($credito_disponible),
        'monto_solicitado' => floatval($amount)
    ]);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // Actualizar crédito usado
    $stmt_update = $conn->prepare("
        UPDATE usuarios 
        SET credito_r11_usado = credito_r11_usado + ?
        WHERE id = ?
    ");
    $stmt_update->bind_param("di", $amount, $user_id);
    $stmt_update->execute();

    // Registrar transacción
    $stmt_trans = $conn->prepare("
        INSERT INTO r11_credit_transactions 
        (user_id, amount, type, description, order_id)
        VALUES (?, ?, 'debit', ?, ?)
    ");
    $description = "Compra orden #$order_id";
    $stmt_trans->bind_param("idss", $user_id, $amount, $description, $order_id);
    $stmt_trans->execute();

    // Actualizar orden en tuu_orders
    $stmt_order = $conn->prepare("
        UPDATE tuu_orders 
        SET pagado_con_credito_r11 = 1, 
            monto_credito_r11 = ?, 
            payment_method = 'r11_credit', 
            payment_status = 'paid'
        WHERE order_number = ?
    ");
    $stmt_order->bind_param("ds", $amount, $order_id);
    $stmt_order->execute();

    $conn->commit();

    $nuevo_disponible = $credito_disponible - $amount;

    echo json_encode([
        'success' => true,
        'message' => 'Crédito aplicado exitosamente',
        'credito_usado' => floatval($amount),
        'credito_disponible' => floatval($nuevo_disponible)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al procesar: ' . $e->getMessage()]);
}

$conn->close();
?>
