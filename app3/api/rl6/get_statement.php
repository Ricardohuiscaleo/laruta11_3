<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

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

// Obtener transacciones de rl6_credit_transactions
$stmt = $conn->prepare("
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
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $items = [];
    
    // Si tiene order_id, obtener items de la venta
    if ($row['order_id']) {
        $stmt_items = $conn->prepare("
            SELECT product_name, quantity, product_price
            FROM tuu_order_items
            WHERE order_reference = ?
        ");
        $stmt_items->bind_param("s", $row['order_id']);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        
        while ($item = $result_items->fetch_assoc()) {
            $items[] = [
                'nombre' => $item['product_name'],
                'cantidad' => intval($item['quantity']),
                'precio' => floatval($item['product_price'])
            ];
        }
    }
    
    $transactions[] = [
        'id' => $row['id'],
        'fecha' => $row['created_at'],
        'monto' => floatval($row['amount']),
        'tipo' => $row['type'],
        'descripcion' => $row['description'],
        'order_id' => $row['order_id'],
        'items' => $items
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
