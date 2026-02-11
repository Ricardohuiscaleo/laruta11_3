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

// Obtener estructura de la tabla
$result = $conn->query("DESCRIBE ventas_v2");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row;
}

// Obtener datos de la tabla
$data = [];
$result = $conn->query("SELECT * FROM ventas_v2");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Cerrar conexión
$conn->close();

// Devolver resultados
echo json_encode([
    'success' => true,
    'message' => 'Test completado',
    'columns' => $columns,
    'data' => $data
]);
?>