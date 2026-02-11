<?php
// Evitar el almacenamiento en caché de las respuestas
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
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

// Consulta SQL para obtener todos los datos de ventas
$sql = "SELECT id, carro_id, precio_promedio, costo_variable, cantidad_vendida, 
               fecha_actualizacion, cargo_1, sueldo_1, cargo_2, sueldo_2, 
               cargo_3, sueldo_3, cargo_4, sueldo_4 
        FROM ventas_v2 
        ORDER BY carro_id";

$result = $conn->query($sql);
$ventas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Asegurarse de que los valores numéricos sean realmente numéricos
        $row['precio_promedio'] = floatval($row['precio_promedio']);
        $row['costo_variable'] = floatval($row['costo_variable']);
        $row['cantidad_vendida'] = intval($row['cantidad_vendida']);
        $row['sueldo_1'] = intval($row['sueldo_1']);
        $row['sueldo_2'] = intval($row['sueldo_2']);
        if ($row['sueldo_3'] !== null) $row['sueldo_3'] = intval($row['sueldo_3']);
        if ($row['sueldo_4'] !== null) $row['sueldo_4'] = intval($row['sueldo_4']);
        
        $ventas[] = $row;
    }
}

// Cerrar conexión
$conn->close();

// Devolver resultados
echo json_encode([
    'success' => true,
    'message' => 'Datos de ventas obtenidos correctamente',
    'ventas' => $ventas
]);
?>