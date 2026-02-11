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

// Calcular la suma de todos los sueldos
$sql_sum = "SELECT SUM(COALESCE(sueldo_1, 0) + COALESCE(sueldo_2, 0) + COALESCE(sueldo_3, 0) + COALESCE(sueldo_4, 0)) AS total_sueldos FROM ventas_v2";
$result_sum = $conn->query($sql_sum);
$row = $result_sum->fetch_assoc();
$total_sueldos = $row['total_sueldos'];

// Actualizar el sueldo_base en costos_fijos_v2
$sql_update = "UPDATE costos_fijos_v2 SET sueldo_base = ? ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql_update);
$stmt->bind_param("d", $total_sueldos);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Sueldo base actualizado correctamente',
        'total_sueldos' => $total_sueldos
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar sueldo base: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>