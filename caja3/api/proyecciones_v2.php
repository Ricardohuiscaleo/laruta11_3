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

// Obtener todas las proyecciones
$sql = "SELECT id, nombre, CONCAT(CASE 
            WHEN mes = 1 THEN 'Enero'
            WHEN mes = 2 THEN 'Febrero'
            WHEN mes = 3 THEN 'Marzo'
            WHEN mes = 4 THEN 'Abril'
            WHEN mes = 5 THEN 'Mayo'
            WHEN mes = 6 THEN 'Junio'
            WHEN mes = 7 THEN 'Julio'
            WHEN mes = 8 THEN 'Agosto'
            WHEN mes = 9 THEN 'Septiembre'
            WHEN mes = 10 THEN 'Octubre'
            WHEN mes = 11 THEN 'Noviembre'
            WHEN mes = 12 THEN 'Diciembre'
        END, ' ', anio) as fecha, 
        JSON_EXTRACT(datos, '$.resultados.ingresos_brutos') as ingresos,
        JSON_EXTRACT(datos, '$.resultados.utilidad') as utilidad
        FROM proyecciones_v2 
        ORDER BY fecha_creacion DESC";

$result = $conn->query($sql);
$proyecciones = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Limpiar los valores JSON (quitar comillas)
        $row['ingresos'] = json_decode($row['ingresos']);
        $row['utilidad'] = json_decode($row['utilidad']);
        $proyecciones[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'data' => $proyecciones
]);

$conn->close();
?>