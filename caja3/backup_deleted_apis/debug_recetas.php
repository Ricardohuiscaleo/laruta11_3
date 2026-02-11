<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir archivo de configuración
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
        $conn = mysqli_connect(
            $config['Calcularuta11_db_host'],
            $config['Calcularuta11_db_user'],
            $config['Calcularuta11_db_pass'],
            $config['Calcularuta11_db_name']
        );
        if($conn === false){
            die(json_encode(["error" => "No se pudo conectar a la base de datos: " . mysqli_connect_error()]));
        }
        mysqli_set_charset($conn, "utf8");
    } else {
        die(json_encode(["error" => "No se encontró el archivo de configuración"]));
    }
}

// Información de depuración
$debug = [
    "tablas" => [],
    "recetas" => [],
    "ingredientes" => [],
    "productos" => []
];

// Verificar tablas existentes
$result = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_array($result)) {
    $debug["tablas"][] = $row[0];
}

// Verificar estructura de la tabla recetas
$result = mysqli_query($conn, "DESCRIBE recetas");
$recetas_estructura = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recetas_estructura[] = $row;
}
$debug["estructura_recetas"] = $recetas_estructura;

// Obtener datos de recetas
$result = mysqli_query($conn, "SELECT * FROM recetas LIMIT 10");
while ($row = mysqli_fetch_assoc($result)) {
    $debug["recetas"][] = $row;
}

// Obtener datos de ingredientes
$result = mysqli_query($conn, "SELECT id, nombre, costo_compra, iva_incluido FROM ingredientes LIMIT 10");
while ($row = mysqli_fetch_assoc($result)) {
    $debug["ingredientes"][] = $row;
}

// Obtener datos de productos
$result = mysqli_query($conn, "SELECT id, nombre, precio FROM productos LIMIT 10");
while ($row = mysqli_fetch_assoc($result)) {
    $debug["productos"][] = $row;
}

// Probar consulta de recetas
$sql = "SELECT r.producto_id, r.ingrediente_id, r.gramos, i.nombre as ingrediente_nombre 
        FROM recetas r 
        LEFT JOIN ingredientes i ON r.ingrediente_id = i.id 
        ORDER BY r.producto_id
        LIMIT 20";
$result = mysqli_query($conn, $sql);

$recetas_test = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recetas_test[] = $row;
}
$debug["recetas_test"] = $recetas_test;

echo json_encode($debug, JSON_PRETTY_PRINT);

$conn->close();
?>