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
if (!isset($_POST['numero_carros']) || !isset($_POST['permisos']) || 
    !isset($_POST['servicios']) || !isset($_POST['otros_fijos'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Datos incompletos: ' . json_encode($_POST)
    ]));
}

$numero_carros = intval($_POST['numero_carros']);
$sueldo_base = isset($_POST['sueldo_base']) ? floatval($_POST['sueldo_base']) : 0;

// Log para depuración
file_put_contents('../debug_log.txt', date('Y-m-d H:i:s') . ' - Sueldo base recibido: ' . $sueldo_base . "\n", FILE_APPEND);
$cargas_sociales = 25; // Valor fijo del 25%
$permisos = floatval($_POST['permisos']);
$servicios = floatval($_POST['servicios']);
$otros_fijos = floatval($_POST['otros_fijos']);

// Validar datos
if ($numero_carros <= 0 || $permisos < 0 || $servicios < 0 || $otros_fijos < 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Valores inválidos'
    ]));
}

// Insertar nuevos datos
$sql = "INSERT INTO costos_fijos_v2 (numero_carros, sueldo_base, cargas_sociales, permisos, servicios, otros_fijos) 
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iddddd", $numero_carros, $sueldo_base, $cargas_sociales, $permisos, $servicios, $otros_fijos);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Costos fijos actualizados correctamente'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar costos fijos: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>