<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

// Día 21 del mes actual = fecha límite de pago
$dia_21_mes_actual = date('Y-m-21');
$hoy = date('Y-m-d');
// Solo puede haber morosos si ya pasó el día 21 de este mes
$vencio_este_mes = ($hoy > $dia_21_mes_actual);
// Inicio del ciclo actual: día 22 del mes anterior
$inicio_ciclo_actual = date('Y-m-22', strtotime('first day of last month'));

$query = "
    SELECT 
        u.id, u.nombre, u.email, u.grado_militar, u.unidad_trabajo,
        u.limite_credito, u.credito_usado,
        (u.limite_credito - u.credito_usado) as credito_disponible,
        u.fecha_ultimo_pago,
        MIN(t.created_at) as primera_compra_unused,
        SUM(CASE WHEN t.type = 'debit' AND DATE(t.created_at) < '{$inicio_ciclo_actual}' THEN t.amount ELSE 0 END) as deuda_ciclo_anterior
    FROM usuarios u
    LEFT JOIN rl6_credit_transactions t ON t.user_id = u.id AND t.type = 'debit'
    WHERE u.es_militar_rl6 = 1 AND u.credito_aprobado = 1
    GROUP BY u.id
    ORDER BY u.nombre ASC
";

$result = $conn->query($query);
$users = [];

while ($row = $result->fetch_assoc()) {
    $credito_usado = floatval($row['credito_usado']);
    $fecha_pago = $row['fecha_ultimo_pago'];
    // Moroso: ya pasó el día 21 + no pagó este mes + tiene deuda de ciclo anterior
    // (deuda generada antes del día 22 del mes pasado = ya debía haberse pagado el 21 de este mes)
    $pago_este_mes = $fecha_pago && substr($fecha_pago, 0, 7) === date('Y-m');
    $deuda_ciclo_anterior = floatval($row['deuda_ciclo_anterior']);
    $es_moroso = $vencio_este_mes && $deuda_ciclo_anterior > 0 && !$pago_este_mes;
    $users[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'email' => $row['email'],
        'grado_militar' => $row['grado_militar'],
        'unidad_trabajo' => $row['unidad_trabajo'],
        'credito_total' => floatval($row['limite_credito']),
        'credito_usado' => $credito_usado,
        'credito_disponible' => floatval($row['credito_disponible']),
        'saldo_pagar' => $credito_usado,
        'fecha_ultimo_pago' => $fecha_pago,
        'es_moroso' => $es_moroso
    ];
}

echo json_encode([
    'success' => true,
    'users' => $users,
    'total' => count($users),
    'vencio_este_mes' => $vencio_este_mes,
    'dia_vencimiento' => $dia_21_mes_actual
]);

$conn->close();
?>
