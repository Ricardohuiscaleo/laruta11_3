<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

// Obtener datos de crédito del usuario
$stmt = $conn->prepare("
    SELECT 
        es_militar_rl6,
        credito_aprobado,
        limite_credito,
        credito_usado,
        grado_militar,
        unidad_trabajo,
        fecha_aprobacion_rl6
    FROM usuarios 
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !$user['es_militar_rl6'] || !$user['credito_aprobado']) {
    echo json_encode([
        'success' => false,
        'error' => 'Usuario no es militar RL6 aprobado',
        'is_militar' => $user['es_militar_rl6'] ?? 0,
        'is_approved' => $user['credito_aprobado'] ?? 0
    ]);
    exit;
}

// Calcular crédito disponible
$credito_disponible = $user['limite_credito'] - $user['credito_usado'];

// Obtener historial de transacciones (últimas 20)
$stmt_trans = $conn->prepare("
    SELECT 
        id,
        amount,
        type,
        description,
        order_id,
        created_at
    FROM rl6_credit_transactions
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
        'limite_credito' => floatval($user['limite_credito']),
        'credito_usado' => floatval($user['credito_usado']),
        'credito_disponible' => floatval($credito_disponible),
        'grado_militar' => $user['grado_militar'],
        'unidad_trabajo' => $user['unidad_trabajo'],
        'fecha_aprobacion' => $user['fecha_aprobacion_rl6']
    ],
    'transactions' => $transactions
]);

$conn->close();
?>
