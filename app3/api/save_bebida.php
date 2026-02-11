<?php
// API para guardar una bebida
header('Content-Type: application/json');
require_once '../config.php';

// Obtener los datos enviados
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['nombre']) || !isset($data['costo']) || !isset($data['precio'])) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

$id = isset($data['id']) ? mysqli_real_escape_string($conn, $data['id']) : null;
$nombre = mysqli_real_escape_string($conn, $data['nombre']);
$categoria = isset($data['categoria']) ? mysqli_real_escape_string($conn, $data['categoria']) : '';
$descripcion = isset($data['descripcion']) ? mysqli_real_escape_string($conn, $data['descripcion']) : '';
$costo = intval($data['costo']);
$precio = intval($data['precio']);

if (empty($nombre) || $costo <= 0 || $precio <= 0) {
    echo json_encode(['error' => 'Nombre, costo o precio inválidos']);
    exit;
}

// Verificar si existe la tabla de bebidas
$query = "SHOW TABLES LIKE 'bebidas'";
$result = mysqli_query($conn, $query);
$tabla_existe = $result && mysqli_num_rows($result) > 0;

if (!$tabla_existe) {
    // Crear la tabla de bebidas
    $query = "CREATE TABLE bebidas (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        categoria VARCHAR(50) DEFAULT NULL,
        descripcion TEXT DEFAULT NULL,
        costo INT(11) NOT NULL,
        precio INT(11) NOT NULL,
        PRIMARY KEY (id)
    )";
    
    if (!mysqli_query($conn, $query)) {
        echo json_encode(['error' => 'Error al crear la tabla de bebidas: ' . mysqli_error($conn)]);
        exit;
    }
}

// Insertar o actualizar la bebida
if ($id) {
    // Actualizar bebida existente
    $query = "UPDATE bebidas SET nombre = '$nombre', categoria = '$categoria', descripcion = '$descripcion', costo = $costo, precio = $precio WHERE id = $id";
} else {
    // Insertar nueva bebida
    $query = "INSERT INTO bebidas (nombre, categoria, descripcion, costo, precio) VALUES ('$nombre', '$categoria', '$descripcion', $costo, $precio)";
}

if (mysqli_query($conn, $query)) {
    $bebida_id = $id ? $id : mysqli_insert_id($conn);
    echo json_encode([
        'success' => true,
        'message' => $id ? 'Bebida actualizada correctamente' : 'Bebida añadida correctamente',
        'id' => $bebida_id
    ]);
} else {
    echo json_encode([
        'error' => 'Error al guardar la bebida: ' . mysqli_error($conn)
    ]);
}
?>
