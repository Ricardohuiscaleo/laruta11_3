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
    $config_path = __DIR__ . '/../config.php';
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
    "recetas_actualizadas" => [],
    "errores" => []
];

// Obtener todas las recetas
$sql = "SELECT r.id, r.producto_id, r.ingrediente_id, r.gramos, p.nombre as producto_nombre 
        FROM recetas r 
        LEFT JOIN productos p ON r.producto_id = p.id 
        ORDER BY r.producto_id";
$result = mysqli_query($conn, $sql);

$recetas = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recetas[] = $row;
}

// Obtener todos los ingredientes
$sql = "SELECT id, nombre FROM ingredientes WHERE id <= 26 ORDER BY id";
$result = mysqli_query($conn, $sql);

$ingredientes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $ingredientes[$row['id']] = $row['nombre'];
}

// Actualizar cada receta
$recetas_actualizadas = 0;
$errores = 0;

foreach ($recetas as $receta) {
    $receta_id = $receta['id'];
    $ingrediente_id = $receta['ingrediente_id'];
    $producto_id = $receta['producto_id'];
    $producto_nombre = $receta['producto_nombre'];
    
    // Verificar si el ingrediente existe en nuestra lista de ingredientes válidos
    if (!isset($ingredientes[$ingrediente_id])) {
        // Buscar un ingrediente similar por nombre en la receta
        $sql = "SELECT i.nombre FROM ingredientes i 
                WHERE i.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $ingrediente_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $nombre_ingrediente = $row ? $row['nombre'] : "Desconocido";
        
        // Buscar un ingrediente válido con ID entre 1 y 26
        $nuevo_id = null;
        foreach ($ingredientes as $id => $nombre) {
            if (stripos($nombre, $nombre_ingrediente) !== false || 
                stripos($nombre_ingrediente, $nombre) !== false) {
                $nuevo_id = $id;
                break;
            }
        }
        
        // Si no encontramos un ingrediente similar, usar el ID 1 (Pan Marraqueta) como predeterminado
        if (!$nuevo_id) {
            $nuevo_id = 1;
        }
        
        // Actualizar la receta con el nuevo ID de ingrediente
        $sql = "UPDATE recetas SET ingrediente_id = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $nuevo_id, $receta_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $recetas_actualizadas++;
            $debug["recetas_actualizadas"][] = [
                "receta_id" => $receta_id,
                "producto" => $producto_nombre,
                "ingrediente_viejo" => $ingrediente_id . " - " . $nombre_ingrediente,
                "ingrediente_nuevo" => $nuevo_id . " - " . $ingredientes[$nuevo_id]
            ];
        } else {
            $errores++;
            $debug["errores"][] = "Error al actualizar receta $receta_id: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Actualizar costo_por_gramo para todos los ingredientes
$sql = "UPDATE ingredientes SET costo_por_gramo = costo_compra / 1000 WHERE costo_por_gramo = 0 OR costo_por_gramo IS NULL";
$result = mysqli_query($conn, $sql);
$ingredientes_actualizados = mysqli_affected_rows($conn);

echo json_encode([
    "success" => true,
    "recetas_actualizadas" => $recetas_actualizadas,
    "ingredientes_actualizados" => $ingredientes_actualizados,
    "errores" => $errores,
    "debug" => $debug
], JSON_PRETTY_PRINT);

$conn->close();
?>