<?php
header('Content-Type: application/json');
require_once '../config.php';

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
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Tabla ingredientes creada correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al crear la tabla ingredientes: ' . $conn->error]);
        $conn->close();
        exit;
    }
} else {
    // Verificar si la columna peso existe
    $result = $conn->query("SHOW COLUMNS FROM ingredientes LIKE 'peso'");
    $pesoExists = ($result && $result->num_rows > 0);
    
    // Si la columna peso no existe, añadirla
    if (!$pesoExists) {
        $sql = "ALTER TABLE ingredientes ADD COLUMN peso DECIMAL(10,2) DEFAULT 0";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Columna peso añadida correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al añadir la columna peso: ' . $conn->error]);
            $conn->close();
            exit;
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'La columna peso ya existe']);
    }
}

// Mostrar la estructura actual de la tabla
$result = $conn->query("DESCRIBE ingredientes");
$columns = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
}

echo json_encode(['success' => true, 'columns' => $columns, 'message' => 'Estructura de la tabla ingredientes']);

$conn->close();
?>