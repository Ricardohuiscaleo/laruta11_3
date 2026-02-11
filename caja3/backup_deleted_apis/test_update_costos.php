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

// Datos de prueba
$numero_carros = 2;
$sueldo_base = 900000; // Suma de los sueldos de todos los empleados
$cargas_sociales = 25;
$permisos = 50000;
$servicios = 100000;
$otros_fijos = 50000;

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