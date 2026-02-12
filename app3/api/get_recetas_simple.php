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

// Consulta simple para obtener todas las recetas
$sql = "SELECT r.producto_id, r.ingrediente_id, r.gramos, i.nombre as ingrediente_nombre 
        FROM recetas r 
        LEFT JOIN ingredientes i ON r.ingrediente_id = i.id 
        ORDER BY r.producto_id";
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(["error" => "Error en la consulta: " . mysqli_error($conn)]);
    exit;
}

$recetas = [];
while ($row = mysqli_fetch_assoc($result)) {
    $producto_id = $row['producto_id'];
    
    if (!isset($recetas[$producto_id])) {
        $recetas[$producto_id] = [];
    }
    
    // Asegurarse de que ingrediente_id sea un entero y no sea nulo
    $ingrediente_id = isset($row['ingrediente_id']) ? (int)$row['ingrediente_id'] : 0;
    if ($ingrediente_id > 0) {
        $recetas[$producto_id][] = [
            "ingrediente_id" => $ingrediente_id,
            "name" => $row['ingrediente_nombre'] ?? 'Ingrediente sin nombre',
            "gramos" => (int)$row['gramos'],
            "grams" => (int)$row['gramos']
        ];
    }
}

echo json_encode($recetas);

$conn->close();
?>