<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

// Obtener usuarios con crédito RL6 aprobado
$query = "
    SELECT 
        id,
        nombre,
        email,
        grado_militar,
        unidad_trabajo,
        limite_credito,
        credito_usado,
        (limite_credito - credito_usado) as credito_disponible
    FROM usuarios
    WHERE es_militar_rl6 = 1 
    AND credito_aprobado = 1
    ORDER BY nombre ASC
";

$result = $conn->query($query);
$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'email' => $row['email'],
        'grado_militar' => $row['grado_militar'],
        'unidad_trabajo' => $row['unidad_trabajo'],
        'credito_total' => floatval($row['limite_credito']),
        'credito_usado' => floatval($row['credito_usado']),
        'credito_disponible' => floatval($row['credito_disponible']),
        'saldo_pagar' => floatval($row['credito_usado'])
    ];
}

echo json_encode([
    'success' => true,
    'users' => $users,
    'total' => count($users)
]);

$conn->close();
?>
