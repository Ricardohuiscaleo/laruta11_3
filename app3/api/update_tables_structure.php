<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Modificar la tabla ventas_v2 para añadir columnas de cargos y sueldos
$sql = "ALTER TABLE ventas_v2 
        ADD COLUMN cargo_1 VARCHAR(100) DEFAULT 'Vendedor',
        ADD COLUMN sueldo_1 INT DEFAULT 500000,
        ADD COLUMN cargo_2 VARCHAR(100) DEFAULT 'Ayudante',
        ADD COLUMN sueldo_2 INT DEFAULT 400000,
        ADD COLUMN cargo_3 VARCHAR(100) DEFAULT NULL,
        ADD COLUMN sueldo_3 INT DEFAULT NULL,
        ADD COLUMN cargo_4 VARCHAR(100) DEFAULT NULL,
        ADD COLUMN sueldo_4 INT DEFAULT NULL";

$result = ["success" => false, "message" => ""];

if ($conn->query($sql) === TRUE) {
    $result["success"] = true;
    $result["message"] = "Columnas de cargos y sueldos añadidas a la tabla ventas_v2 correctamente";
} else {
    $result["message"] = "Error al añadir columnas a ventas_v2: " . $conn->error;
}

// Cerrar conexión
$conn->close();

echo json_encode($result);
?>