<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

$conn = getDBConnection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

// Obtener datos del usuario
$stmt = $conn->prepare("
    SELECT nombre, email, grado_militar, unidad_trabajo, limite_credito, credito_usado, fecha_aprobacion_rl6
    FROM usuarios 
    WHERE id = ? AND es_militar_rl6 = 1 AND credito_aprobado = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

// Obtener transacciones (ventas con crédito RL6)
$stmt = $conn->prepare("
    SELECT 
        v.id,
        v.fecha_hora,
        v.total,
        v.estado,
        v.metodo_pago,
        v.tipo_pedido,
        GROUP_CONCAT(CONCAT(vi.cantidad, 'x ', p.nombre) SEPARATOR ', ') as items
    FROM ventas v
    LEFT JOIN ventas_items vi ON v.id = vi.venta_id
    LEFT JOIN productos p ON vi.producto_id = p.id
    WHERE v.usuario_id = ? 
    AND v.metodo_pago = 'credito_rl6'
    GROUP BY v.id
    ORDER BY v.fecha_hora DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = [
        'id' => $row['id'],
        'fecha' => $row['fecha_hora'],
        'monto' => floatval($row['total']),
        'tipo' => 'compra',
        'estado' => $row['estado'],
        'descripcion' => $row['items'] ?: 'Compra',
        'tipo_pedido' => $row['tipo_pedido']
    ];
}

echo json_encode([
    'success' => true,
    'user' => [
        'nombre' => $user['nombre'],
        'email' => $user['email'],
        'grado_militar' => $user['grado_militar'],
        'unidad_trabajo' => $user['unidad_trabajo'],
        'credito_total' => floatval($user['limite_credito']),
        'credito_usado' => floatval($user['credito_usado']),
        'credito_disponible' => floatval($user['limite_credito']) - floatval($user['credito_usado']),
        'fecha_aprobacion' => $user['fecha_aprobacion_rl6']
    ],
    'transactions' => $transactions,
    'total_transactions' => count($transactions)
]);

$conn->close();
?>
