<?php
// Configurar encabezados para evitar caché
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../config.php';

// Verificar que se proporcionó un ID
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

$id = $_GET['id'];

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Verificar si la tabla ingredientes existe
$tableExists = false;
$result = $conn->query("SHOW TABLES LIKE 'ingredientes'");
if ($result && $result->num_rows > 0) {
    $tableExists = true;
}

// Si la tabla no existe, crearla
if (!$tableExists) {
    $sql = "CREATE TABLE ingredientes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        categoria VARCHAR(100) DEFAULT NULL,
        costo_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
        costo_neto DECIMAL(10,2) DEFAULT NULL,
        iva_incluido TINYINT(1) DEFAULT 1,
        costo_por_gramo DECIMAL(10,2) DEFAULT 0,
        unidad_nombre VARCHAR(20) DEFAULT 'kg',
        unidad_gramos INT(11) DEFAULT 1000,
        stock DECIMAL(10,2) DEFAULT 0,
        peso DECIMAL(10,2) DEFAULT 0,
        PRIMARY KEY (id)
    )";
    
    if (!$conn->query($sql)) {
        die(json_encode(['success' => false, 'error' => 'Error al crear la tabla ingredientes: ' . $conn->error]));
    }
    
    echo json_encode(['success' => false, 'error' => 'Ingrediente no encontrado, tabla creada']);
    $stmt->close();
    $conn->close();
    exit;
} else {
    // Verificar si la columna peso existe
    $result = $conn->query("SHOW COLUMNS FROM ingredientes LIKE 'peso'");
    $pesoExists = ($result && $result->num_rows > 0);
    
    // Si la columna peso no existe, añadirla
    if (!$pesoExists) {
        $sql = "ALTER TABLE ingredientes ADD COLUMN peso DECIMAL(10,2) DEFAULT 0";
        if (!$conn->query($sql)) {
            die(json_encode(['success' => false, 'error' => 'Error al añadir la columna peso: ' . $conn->error]));
        }
    }
}

// Consultar ingrediente
$sql = "SELECT * FROM ingredientes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $ingrediente = $result->fetch_assoc();
    
    // Convertir valores numéricos correctamente
    $ingrediente['costo_compra'] = floatval($ingrediente['costo_compra']);
    $ingrediente['costo_neto'] = floatval($ingrediente['costo_neto']);
    $ingrediente['costo_por_gramo'] = floatval($ingrediente['costo_por_gramo']);
    $ingrediente['peso'] = floatval($ingrediente['peso']);
    $ingrediente['stock'] = floatval($ingrediente['stock']);
    $ingrediente['unidad_gramos'] = floatval($ingrediente['unidad_gramos']);
    
    // Convertir valores booleanos
    $ingrediente['iva_incluido'] = (bool)$ingrediente['iva_incluido'];
    
    echo json_encode($ingrediente);
} else {
    echo json_encode(['success' => false, 'error' => 'Ingrediente no encontrado']);
}

$stmt->close();
$conn->close();
?>