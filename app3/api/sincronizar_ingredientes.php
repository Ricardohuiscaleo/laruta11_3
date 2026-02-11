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
    "ingredientes_en_recetas" => [],
    "ingredientes_en_bd" => [],
    "ingredientes_faltantes" => [],
    "acciones" => []
];

// Obtener todos los IDs de ingredientes usados en recetas
$sql = "SELECT DISTINCT ingrediente_id FROM recetas ORDER BY ingrediente_id";
$result = mysqli_query($conn, $sql);
$ingredientes_en_recetas = [];
while ($row = mysqli_fetch_assoc($result)) {
    $ingredientes_en_recetas[] = (int)$row['ingrediente_id'];
}
$debug["ingredientes_en_recetas"] = $ingredientes_en_recetas;

// Obtener todos los IDs de ingredientes en la base de datos
$sql = "SELECT id, nombre FROM ingredientes ORDER BY id";
$result = mysqli_query($conn, $sql);
$ingredientes_en_bd = [];
while ($row = mysqli_fetch_assoc($result)) {
    $ingredientes_en_bd[(int)$row['id']] = $row['nombre'];
}
$debug["ingredientes_en_bd"] = $ingredientes_en_bd;

// Encontrar ingredientes faltantes
$ingredientes_faltantes = [];
foreach ($ingredientes_en_recetas as $id) {
    if (!isset($ingredientes_en_bd[$id])) {
        $ingredientes_faltantes[] = $id;
    }
}
$debug["ingredientes_faltantes"] = $ingredientes_faltantes;

// Crear ingredientes faltantes
$ingredientes_creados = 0;
foreach ($ingredientes_faltantes as $id) {
    $nombre = "Ingrediente #" . $id;
    $sql = "INSERT INTO ingredientes (id, nombre, costo_compra, costo_por_gramo, iva_incluido) VALUES (?, ?, 1000, 1, 1)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $id, $nombre);
    
    if (mysqli_stmt_execute($stmt)) {
        $ingredientes_creados++;
        $debug["acciones"][] = "Creado ingrediente con ID $id y nombre '$nombre'";
    } else {
        $debug["acciones"][] = "Error al crear ingrediente con ID $id: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

// Actualizar costo_por_gramo para todos los ingredientes
$ingredientes_actualizados = 0;
$sql = "UPDATE ingredientes SET costo_por_gramo = costo_compra / 1000 WHERE costo_por_gramo = 0 OR costo_por_gramo IS NULL";
$result = mysqli_query($conn, $sql);
if ($result) {
    $ingredientes_actualizados = mysqli_affected_rows($conn);
    $debug["acciones"][] = "Actualizados $ingredientes_actualizados ingredientes con costo_por_gramo";
} else {
    $debug["acciones"][] = "Error al actualizar costo_por_gramo: " . mysqli_error($conn);
}

echo json_encode([
    "success" => true,
    "ingredientes_creados" => $ingredientes_creados,
    "ingredientes_actualizados" => $ingredientes_actualizados,
    "debug" => $debug
], JSON_PRETTY_PRINT);

$conn->close();
?>