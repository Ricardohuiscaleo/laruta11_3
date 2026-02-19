<?php
header('Content-Type: application/json');
set_time_limit(300); // 5 minutos para procesar todos los envíos

$config = require_once __DIR__ . '/../../config.php';

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

// Obtener todos los usuarios RL6 con crédito aprobado
$query = "
    SELECT id, nombre, email, credito_usado
    FROM usuarios
    WHERE es_militar_rl6 = 1 
    AND credito_aprobado = 1
    ORDER BY nombre ASC
";

$result = $conn->query($query);
$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$conn->close();

if (count($users) === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'No hay usuarios RL6 con crédito aprobado'
    ]);
    exit;
}

// Enviar email a cada usuario
$sent = 0;
$failed = 0;
$errors = [];

foreach ($users as $user) {
    // Llamar al endpoint individual de envío
    $url = 'https://caja.laruta11.cl/api/gmail/send_credit_statement.php?user_id=' . $user['id'];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if ($data && $data['success']) {
            $sent++;
        } else {
            $failed++;
            $errors[] = [
                'user' => $user['nombre'],
                'email' => $user['email'],
                'error' => $data['error'] ?? 'Error desconocido'
            ];
        }
    } else {
        $failed++;
        $errors[] = [
            'user' => $user['nombre'],
            'email' => $user['email'],
            'error' => 'HTTP ' . $http_code
        ];
    }
    
    // Pequeña pausa entre envíos para no saturar Gmail API
    usleep(500000); // 0.5 segundos
}

echo json_encode([
    'success' => true,
    'total_users' => count($users),
    'sent' => $sent,
    'failed' => $failed,
    'errors' => $errors
]);
?>
