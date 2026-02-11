<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir archivo de configuración
require_once '../config.php';

// Parámetros de paginación y búsqueda
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$offset = ($page - 1) * $limit;

// Construir la consulta SQL
$sql_count = "SELECT COUNT(*) as total FROM proyecciones_financieras";
$sql = "SELECT * FROM proyecciones_financieras";

// Añadir condición de búsqueda si se proporciona
if (!empty($search)) {
    $search_condition = " WHERE nombre LIKE '%$search%' OR notas LIKE '%$search%'";
    $sql_count .= $search_condition;
    $sql .= $search_condition;
}

// Añadir ordenamiento y límites
$sql .= " ORDER BY fecha_creacion DESC LIMIT $offset, $limit";

// Ejecutar consulta para contar registros
$result_count = mysqli_query($conn, $sql_count);
$row_count = mysqli_fetch_assoc($result_count);
$total_records = $row_count['total'];
$total_pages = ceil($total_records / $limit);

// Ejecutar consulta principal
$result = mysqli_query($conn, $sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error en la consulta: " . mysqli_error($conn)
    ]);
    exit;
}

// Preparar respuesta
$proyecciones = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Formatear fecha
    $fecha = new DateTime($row['fecha_creacion']);
    $row['fecha_formateada'] = $fecha->format('d/m/Y');
    
    // Añadir a la lista
    $proyecciones[] = $row;
}

// Devolver respuesta
echo json_encode([
    "success" => true,
    "proyecciones" => $proyecciones,
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total_records" => $total_records,
        "total_pages" => $total_pages
    ]
]);

mysqli_close($conn);
?>