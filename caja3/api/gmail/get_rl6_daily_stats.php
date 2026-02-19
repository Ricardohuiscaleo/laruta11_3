<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

$config = require_once __DIR__ . '/../../config.php';

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

// Obtener fecha de hoy
$today = date('Y-m-d');

// Pagos del día (desde tuu_orders con RL6-)
$query_pagos = "
    SELECT 
        COUNT(DISTINCT user_id) as usuarios_pagaron,
        SUM(installment_amount) as total_pagado
    FROM tuu_orders
    WHERE order_number LIKE 'RL6-%'
    AND payment_status = 'paid'
    AND DATE(updated_at) = ?
";

$stmt = $conn->prepare($query_pagos);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
$pagos = $result->fetch_assoc();

// Deuda total actual (suma de credito_usado de todos los usuarios RL6)
$query_deuda = "
    SELECT 
        COUNT(*) as usuarios_con_deuda,
        SUM(credito_usado) as deuda_total
    FROM usuarios
    WHERE es_militar_rl6 = 1 
    AND credito_aprobado = 1
    AND credito_usado > 0
";

$result_deuda = $conn->query($query_deuda);
$deuda = $result_deuda->fetch_assoc();

echo json_encode([
    'success' => true,
    'fecha' => $today,
    'pagos_hoy' => [
        'usuarios_pagaron' => intval($pagos['usuarios_pagaron']),
        'total_pagado' => floatval($pagos['total_pagado'] ?? 0)
    ],
    'deuda_actual' => [
        'usuarios_con_deuda' => intval($deuda['usuarios_con_deuda']),
        'deuda_total' => floatval($deuda['deuda_total'] ?? 0)
    ]
]);

$conn->close();
?>
