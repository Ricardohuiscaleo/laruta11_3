<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

// Fecha límite del mes anterior (día 21)
$mes_anterior_21 = date('Y-m-21', strtotime('first day of last month'));

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
    // Moroso: tiene deuda Y no pagó antes del día 21 del mes anterior
    $es_moroso = $credito_usado > 0 && ($fecha_pago === null || $fecha_pago < $mes_anterior_21);
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
    'total' => count($users)
]);

$conn->close();
?>
