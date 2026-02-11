<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir archivo de configuración
require_once '../config.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Se requiere un ID de proyección"
    ]);
    exit;
}

$id = intval($_GET['id']);

// Consulta para obtener la proyección
$sql = "SELECT * FROM proyecciones_financieras WHERE id = $id";
$result = mysqli_query($conn, $sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error en la consulta: " . mysqli_error($conn)
    ]);
    exit;
}

if (mysqli_num_rows($result) == 0) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "error" => "Proyección no encontrada"
    ]);
    exit;
}

// Obtener datos de la proyección
$proyeccion = mysqli_fetch_assoc($result);

// Añadir nombre del mes
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$proyeccion['periodo_mes_nombre'] = $meses[$proyeccion['periodo_mes']] ?? '';

// Consulta para obtener los detalles de la proyección (carros)
$sql_detalles = "SELECT * FROM detalles_proyeccion WHERE proyeccion_id = $id";
$result_detalles = mysqli_query($conn, $sql_detalles);

$detalles = [];
if ($result_detalles) {
    while ($row = mysqli_fetch_assoc($result_detalles)) {
        $detalles[] = $row;
    }
}

// Añadir detalles a la respuesta
$proyeccion['detalles'] = $detalles;

// Devolver respuesta
echo json_encode([
    "success" => true,
    "data" => $proyeccion
]);

mysqli_close($conn);
?>