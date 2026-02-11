<?php
// Configurar encabezados para evitar caché
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../config.php';

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || !isset($data['type'])) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
    exit;
}

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Verificar si la tabla categorias existe
$tableExists = false;
$result = $conn->query("SHOW TABLES LIKE 'categorias'");
if ($result && $result->num_rows > 0) {
    $tableExists = true;
}

// Si la tabla no existe, crearla
if (!$tableExists) {
    $sql = "CREATE TABLE categorias (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id)
    )";
    
    if (!$conn->query($sql)) {
        echo json_encode(['success' => false, 'error' => 'Error al crear la tabla categorias: ' . $conn->error]);
        $conn->close();
        exit;
    }
}

// Verificar si la categoría ya existe
$name = $conn->real_escape_string($data['name']);
$type = $conn->real_escape_string($data['type']);

$sql = "SELECT * FROM categorias WHERE name = '{$name}' AND type = '{$type}'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'La categoría ya existe']);
    $conn->close();
    exit;
}

// Insertar la nueva categoría
$sql = "INSERT INTO categorias (name, type) VALUES ('{$name}', '{$type}')";
if ($conn->query($sql)) {
    $id = $conn->insert_id;
    echo json_encode(['success' => true, 'message' => 'Categoría guardada correctamente', 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al guardar categoría: ' . $conn->error]);
}

$conn->close();
?>