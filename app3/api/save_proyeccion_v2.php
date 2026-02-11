<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Verificar que se recibieron los datos necesarios
if (!isset($_POST['nombre']) || !isset($_POST['mes']) || 
    !isset($_POST['anio']) || !isset($_POST['datos'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Datos incompletos'
    ]));
}

$nombre = $conn->real_escape_string($_POST['nombre']);
$mes = intval($_POST['mes']);
$anio = intval($_POST['anio']);
$notas = isset($_POST['notas']) ? $conn->real_escape_string($_POST['notas']) : '';
$datos = $conn->real_escape_string($_POST['datos']);

// Validar datos
if (empty($nombre) || $mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
    die(json_encode([
        'success' => false,
        'message' => 'Valores inválidos'
    ]));
}

// Insertar proyección
$sql = "INSERT INTO proyecciones_v2 (nombre, mes, anio, notas, datos) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("siiss", $nombre, $mes, $anio, $notas, $datos);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Proyección guardada correctamente',
        'id' => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar proyección: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>