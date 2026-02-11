<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

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

$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? null;
$amount = $input['amount'] ?? null;
$order_id = $input['order_id'] ?? null;

if (!$user_id || !$amount || !$order_id) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Verificar que es militar aprobado
$stmt = $conn->prepare("
    SELECT 
        es_militar_rl6,
        credito_aprobado,
        limite_credito,
        credito_usado
    FROM usuarios 
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['es_militar_rl6'] || !$user['credito_aprobado']) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autorizado para usar crédito RL6']);
    exit;
}

// Validar saldo disponible
$credito_disponible = $user['limite_credito'] - $user['credito_usado'];

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
        SET credito_usado = credito_usado + ?
        WHERE id = ?
    ");
    $stmt_update->bind_param("di", $amount, $user_id);
    $stmt_update->execute();
    
    // Registrar transacción
    $stmt_trans = $conn->prepare("
        INSERT INTO rl6_credit_transactions 
        (user_id, amount, type, description, order_id)
        VALUES (?, ?, 'debit', ?, ?)
    ");
    $description = "Compra orden #$order_id";
    $stmt_trans->bind_param("idss", $user_id, $amount, $description, $order_id);
    $stmt_trans->execute();
    
    // Actualizar orden en tuu_orders
    $stmt_order = $conn->prepare("
        UPDATE tuu_orders 
        SET pagado_con_credito_rl6 = 1, monto_credito_rl6 = ?
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
