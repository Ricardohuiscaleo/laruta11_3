<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

// Ciclo: día 22 del mes anterior → día 21 del mes actual
// Moroso = pasó el día 21 + tiene deuda de compras del ciclo vencido + no pagó este mes
$hoy = date('Y-m-d');
$dia_21_mes_actual = date('Y-m-21');
$vencio_este_mes = ($hoy > $dia_21_mes_actual);
// Inicio del ciclo vencido: día 22 del mes anterior
$inicio_ciclo_vencido = date('Y-m-22', strtotime('first day of last month'));
// Fin del ciclo vencido: día 21 del mes actual (inclusive)
$fin_ciclo_vencido = $dia_21_mes_actual;

$query = "
    SELECT 
        u.id, u.nombre, u.email, u.telefono, u.grado_militar, u.unidad_trabajo,
        u.limite_credito, u.credito_usado,
        (u.limite_credito - u.credito_usado) as credito_disponible,
        u.fecha_ultimo_pago,
        COALESCE(SUM(CASE 
            WHEN t.type = 'debit' 
            AND DATE(t.created_at) >= '{$inicio_ciclo_vencido}' 
            AND DATE(t.created_at) <= '{$fin_ciclo_vencido}'
            THEN t.amount ELSE 0 
        END), 0) as deuda_ciclo_vencido,
        COALESCE(SUM(CASE 
            WHEN t.type = 'refund' 
            AND DATE_FORMAT(t.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
            THEN t.amount ELSE 0 
        END), 0) as pagado_este_mes
    FROM usuarios u
    LEFT JOIN rl6_credit_transactions t ON t.user_id = u.id
    WHERE u.es_militar_rl6 = 1 AND u.credito_aprobado = 1
    GROUP BY u.id
    ORDER BY u.nombre ASC
";

$result = $conn->query($query);
$users = [];

while ($row = $result->fetch_assoc()) {
    $credito_usado = floatval($row['credito_usado']);
    $fecha_pago = $row['fecha_ultimo_pago'];
    $deuda_ciclo_vencido = floatval($row['deuda_ciclo_vencido']);
    // Moroso: pasó el día 21 + tiene deuda del ciclo vencido + no pagó este mes
    $pago_este_mes = $fecha_pago && substr($fecha_pago, 0, 7) === date('Y-m');
    $es_moroso = $vencio_este_mes && $deuda_ciclo_vencido > 0 && !$pago_este_mes;
    $users[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'email' => $row['email'],
        'telefono' => $row['telefono'],
        'grado_militar' => $row['grado_militar'],
        'unidad_trabajo' => $row['unidad_trabajo'],
        'credito_total' => floatval($row['limite_credito']),
        'credito_usado' => $credito_usado,
        'credito_disponible' => floatval($row['credito_disponible']),
        'saldo_pagar' => $deuda_ciclo_vencido,
        'fecha_ultimo_pago' => $fecha_pago,
        'pagado_este_mes' => floatval($row['pagado_este_mes']),
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
