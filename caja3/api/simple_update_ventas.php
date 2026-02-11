<?php
header('Content-Type: application/json');
require_once '../config.php';

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Valores fijos para prueba
$carro_id = 1;
$precio_promedio = 5000;
$costo_variable = 40;
$cantidad_vendida = 30;

// Actualizar solo los campos básicos
$sql = "UPDATE ventas_v2 SET 
        precio_promedio = ?, 
        costo_variable = ?, 
        cantidad_vendida = ? 
        WHERE carro_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die(json_encode([
        'success' => false,
        'message' => 'Error en prepare: ' . $conn->error
    ]));
}

$stmt->bind_param("ddii", $precio_promedio, $costo_variable, $cantidad_vendida, $carro_id);
if (!$stmt->execute()) {
    die(json_encode([
        'success' => false,
        'message' => 'Error en execute: ' . $stmt->error
    ]));
}

// Cerrar conexión
$stmt->close();
$conn->close();

// Devolver resultados
echo json_encode([
    'success' => true,
    'message' => 'Actualización simple completada',
    'affected_rows' => $stmt->affected_rows
]);
?>