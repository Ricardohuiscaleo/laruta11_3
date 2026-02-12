<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
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

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID no proporcionado']);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        id, nombre, email, telefono, foto_perfil, google_id,
        es_militar_rl6, credito_aprobado, limite_credito, credito_usado,
        rut, grado_militar, unidad_trabajo
    FROM usuarios 
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
}

$stmt->close();
$conn->close();
?>
