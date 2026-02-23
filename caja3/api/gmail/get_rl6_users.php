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

$query = "
    SELECT 
        id, nombre, email, grado_militar, unidad_trabajo,
        limite_credito, credito_usado,
        (limite_credito - credito_usado) as credito_disponible,
        fecha_ultimo_pago
    FROM usuarios
    WHERE es_militar_rl6 = 1 AND credito_aprobado = 1
    ORDER BY nombre ASC
";

$result = $conn->query($query);
$users = [];

while ($row = $result->fetch_assoc()) {
    $credito_usado = floatval($row['credito_usado']);
    $fecha_pago = $row['fecha_ultimo_pago'];
    // Moroso: ya pasó el día 21 + tiene deuda + no pagó este mes
    $pago_este_mes = $fecha_pago && substr($fecha_pago, 0, 7) === date('Y-m');
    $es_moroso = $vencio_este_mes && $credito_usado > 0 && !$pago_este_mes;
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
