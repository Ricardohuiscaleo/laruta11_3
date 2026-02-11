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

// Obtener datos de ventas exactamente como están en la base de datos
$sql = "SELECT * FROM ventas_v2 ORDER BY carro_id";
$result = $conn->query($sql);
$ventas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ventas[] = $row;
    }
}

// Cerrar conexión
$conn->close();

// Devolver resultados sin ninguna modificación
echo json_encode([
    'success' => true,
    'message' => 'Datos sin procesar de la base de datos',
    'ventas' => $ventas
]);
?>