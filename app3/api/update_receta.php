<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir archivo de configuración
// Primero intentamos con la configuración local
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    // Si no existe, intentamos con la configuración global
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
        
        // Configurar la conexión a la base de datos usando los valores del config global
        $conn = mysqli_connect(
            $config['Calcularuta11_db_host'],
            $config['Calcularuta11_db_user'],
            $config['Calcularuta11_db_pass'],
            $config['Calcularuta11_db_name']
        );
        
        // Verificar la conexión
        if($conn === false){
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "No se pudo conectar a la base de datos: " . mysqli_connect_error()]);
            exit;
        }
        
        // Configurar el conjunto de caracteres a utf8
        mysqli_set_charset($conn, "utf8");
    } else {
        http_response_code(500);
        echo json_encode(["error" => "No se encontró el archivo de configuración"]);
        exit;
    }
}

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
    exit;
}

// Obtener datos de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Verificar que se proporcionaron los datos necesarios
if (!isset($data['producto_id']) || !isset($data['ingredientes'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Faltan datos requeridos (producto_id, ingredientes)"]);
    exit;
}

$producto_id = $data['producto_id'];
$ingredientes = $data['ingredientes'];

// Iniciar transacción
$conn->begin_transaction();

try {
    // Eliminar la receta actual
    $sql_delete = "DELETE FROM recetas WHERE producto_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("s", $producto_id);
    $stmt_delete->execute();
    
    // Insertar los nuevos ingredientes
    if (!empty($ingredientes)) {
        $sql_insert = "INSERT INTO recetas (producto_id, ingrediente_id, gramos) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        
        foreach ($ingredientes as $ingrediente) {
            $ingrediente_id = $ingrediente['ingrediente_id'];
            $gramos = $ingrediente['gramos'];
            
            $stmt_insert->bind_param("sii", $producto_id, $ingrediente_id, $gramos);
            $stmt_insert->execute();
        }
    }
    
    // Confirmar la transacción
    $conn->commit();
    
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al actualizar la receta: " . $e->getMessage()]);
}

$conn->close();
?>