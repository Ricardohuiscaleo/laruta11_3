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

// Verificar si la columna costo_por_gramo existe
$result = mysqli_query($conn, "SHOW COLUMNS FROM ingredientes LIKE 'costo_por_gramo'");
$costo_por_gramo_exists = mysqli_num_rows($result) > 0;

// Actualizar costo_por_gramo para todos los ingredientes
$updated = 0;
$errors = [];

// Obtener todos los ingredientes
$result = mysqli_query($conn, "SELECT id, nombre, costo_compra, unidad_gramos FROM ingredientes");
while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $nombre = $row['nombre'];
    $costo_compra = floatval($row['costo_compra']);
    $unidad_gramos = floatval($row['unidad_gramos']) > 0 ? floatval($row['unidad_gramos']) : 1000;
    
    // Calcular costo por gramo
    $costo_por_gramo = $costo_compra / $unidad_gramos;
    
    // Actualizar el ingrediente
    $update_sql = "UPDATE ingredientes SET costo_por_gramo = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "di", $costo_por_gramo, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $updated++;
    } else {
        $errors[] = "Error al actualizar ingrediente $nombre (ID: $id): " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

echo json_encode([
    "success" => true,
    "message" => "Ingredientes actualizados: $updated",
    "errors" => $errors
]);

$conn->close();
?>