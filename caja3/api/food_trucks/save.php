<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

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

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión']));
}

$id = $_POST['id'] ?? '';
$nombre = $_POST['nombre'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$direccion = $_POST['direccion'] ?? '';
$latitud = $_POST['latitud'] ?? '';
$longitud = $_POST['longitud'] ?? '';
$horario_inicio = $_POST['horario_inicio'] ?? '10:00';
$horario_fin = $_POST['horario_fin'] ?? '22:00';
$activo = $_POST['activo'] ?? 1;
$tarifa_delivery = $_POST['tarifa_delivery'] ?? 2000;

if (empty($nombre) || empty($direccion) || empty($latitud) || empty($longitud)) {
    die(json_encode(['success' => false, 'error' => 'Campos requeridos faltantes']));
}

if ($id) {
    // Actualizar
    $stmt = $conn->prepare("UPDATE food_trucks SET nombre=?, descripcion=?, direccion=?, latitud=?, longitud=?, horario_inicio=?, horario_fin=?, activo=?, tarifa_delivery=? WHERE id=?");
    $stmt->bind_param('sssssssiis', $nombre, $descripcion, $direccion, $latitud, $longitud, $horario_inicio, $horario_fin, $activo, $tarifa_delivery, $id);
} else {
    // Crear
    $stmt = $conn->prepare("INSERT INTO food_trucks (nombre, descripcion, direccion, latitud, longitud, horario_inicio, horario_fin, activo, tarifa_delivery) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssssii', $nombre, $descripcion, $direccion, $latitud, $longitud, $horario_inicio, $horario_fin, $activo, $tarifa_delivery);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error guardando food truck']);
}

$conn->close();
?>