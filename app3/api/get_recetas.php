<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir archivo de configuración
// Primero intentamos con la configuración local
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    // Si no existe, intentamos con la configuración global
    $config_path = __DIR__ . '/../config.php';
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
            echo json_encode(["error" => "No se pudo conectar a la base de datos: " . mysqli_connect_error()]);
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

// Obtener ID del producto si se proporciona
$producto_id = isset($_GET['producto_id']) ? $_GET['producto_id'] : null;

// Consulta SQL
if ($producto_id) {
    // Obtener receta para un producto específico
    $sql = "SELECT r.*, i.nombre as name FROM recetas r 
            LEFT JOIN ingredientes i ON r.ingrediente_id = i.id 
            WHERE r.producto_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $receta = [];
    while ($row = $result->fetch_assoc()) {
        $receta[] = [
            "ingrediente_id" => (int)$row["ingrediente_id"],
            "name" => $row["name"],
            "grams" => (int)$row["gramos"]
        ];
    }
    
    echo json_encode($receta);
} else {
    // Obtener todas las recetas agrupadas por producto
    $sql = "SELECT p.id as producto_id, p.name as producto_nombre, 
            r.ingrediente_id, r.gramos, i.name as ingrediente_nombre 
            FROM productos p 
            LEFT JOIN recetas r ON p.id = r.producto_id 
            LEFT JOIN ingredientes i ON r.ingrediente_id = i.id 
            ORDER BY p.name ASC";
    $result = $conn->query($sql);
    
    $recetas = [];
    $current_producto = null;
    
    while ($row = $result->fetch_assoc()) {
        $producto_id = $row["producto_id"];
        
        if (!isset($recetas[$producto_id])) {
            $recetas[$producto_id] = [
                "producto_id" => $producto_id,
                "producto_nombre" => $row["producto_nombre"],
                "ingredientes" => []
            ];
        }
        
        if ($row["ingrediente_id"]) {
            $recetas[$producto_id]["ingredientes"][] = [
                "ingrediente_id" => (int)$row["ingrediente_id"],
                "name" => $row["ingrediente_nombre"],
                "grams" => (int)$row["gramos"]
            ];
        }
    }
    
    echo json_encode(array_values($recetas));
}

$conn->close();
?>