<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

$rut = $_POST['rut'] ?? null;

if (!$rut) {
    echo json_encode(['success' => false, 'error' => 'RUT no proporcionado']);
    exit;
}

// Limpiar RUT (remover puntos y guión)
$rut_limpio = preg_replace('/[^0-9kK]/', '', $rut);

// Verificar si el RUT ya existe
$stmt = $conn->prepare("SELECT id, es_militar_rl6, credito_aprobado FROM usuarios WHERE REPLACE(REPLACE(rut, '.', ''), '-', '') = ?");
$stmt->bind_param("s", $rut_limpio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // RUT ya existe
    $status = 'pendiente';
    if ($user['credito_aprobado'] == 1) {
        $status = 'aprobado';
    } elseif ($user['credito_aprobado'] == 2) {
        $status = 'rechazado';
    }
    
    echo json_encode([
        'success' => false,
        'exists' => true,
        'status' => $status,
        'message' => 'Este RUT ya está registrado en nuestro sistema.'
    ]);
} else {
    // RUT disponible
    echo json_encode([
        'success' => true,
        'exists' => false,
        'message' => 'RUT disponible'
    ]);
}

$stmt->close();
$conn->close();
?>
