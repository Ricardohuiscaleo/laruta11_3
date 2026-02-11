<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
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
            "ingrediente_id" => intval($row["ingrediente_id"]),
            "name" => $row["name"],
            "grams" => intval($row["gramos"]),
            "gramos" => intval($row["gramos"])
        ];
    }
    
    echo json_encode($receta);
} else {
    // Obtener todas las recetas agrupadas por producto
    $sql = "SELECT r.producto_id, r.ingrediente_id, r.gramos, i.nombre as ingrediente_nombre 
            FROM recetas r 
            LEFT JOIN ingredientes i ON r.ingrediente_id = i.id 
            ORDER BY r.producto_id";
    $result = $conn->query($sql);
    
    $recetas = [];
    
    while ($row = $result->fetch_assoc()) {
        $producto_id = $row["producto_id"];
        
        if (!isset($recetas[$producto_id])) {
            $recetas[$producto_id] = [];
        }
        
        $recetas[$producto_id][] = [
            "ingrediente_id" => intval($row["ingrediente_id"]),
            "name" => $row["ingrediente_nombre"],
            "grams" => intval($row["gramos"]),
            "gramos" => intval($row["gramos"])
        ];
    }
    
    echo json_encode($recetas);
}

$conn->close();
?>