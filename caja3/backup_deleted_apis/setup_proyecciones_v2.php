<?php
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Crear tabla de activos
$sql_activos = "CREATE TABLE IF NOT EXISTS activos_v2 (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    valor_activos DECIMAL(12,2) NOT NULL DEFAULT 0,
    vida_util INT(11) NOT NULL DEFAULT 10,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_activos) === FALSE) {
    echo "Error creando tabla activos_v2: " . $conn->error;
}

// Crear tabla de costos fijos
$sql_costos_fijos = "CREATE TABLE IF NOT EXISTS costos_fijos_v2 (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    numero_carros INT(11) NOT NULL DEFAULT 1,
    sueldo_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    cargas_sociales DECIMAL(5,2) NOT NULL DEFAULT 0,
    permisos DECIMAL(12,2) NOT NULL DEFAULT 0,
    servicios DECIMAL(12,2) NOT NULL DEFAULT 0,
    otros_fijos DECIMAL(12,2) NOT NULL DEFAULT 0,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_costos_fijos) === FALSE) {
    echo "Error creando tabla costos_fijos_v2: " . $conn->error;
}

// Crear tabla de ventas
$sql_ventas = "CREATE TABLE IF NOT EXISTS ventas_v2 (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    carro_id INT(11) NOT NULL,
    precio_promedio DECIMAL(12,2) NOT NULL DEFAULT 0,
    costo_variable DECIMAL(5,2) NOT NULL DEFAULT 0,
    cantidad_vendida INT(11) NOT NULL DEFAULT 0,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_ventas) === FALSE) {
    echo "Error creando tabla ventas_v2: " . $conn->error;
}

// Crear tabla de proyecciones
$sql_proyecciones = "CREATE TABLE IF NOT EXISTS proyecciones_v2 (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    mes INT(2) NOT NULL,
    anio INT(4) NOT NULL,
    notas TEXT,
    datos JSON,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_proyecciones) === FALSE) {
    echo "Error creando tabla proyecciones_v2: " . $conn->error;
}

// Insertar datos iniciales en activos si no existen
$sql_check_activos = "SELECT COUNT(*) as count FROM activos_v2";
$result = $conn->query($sql_check_activos);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $sql_insert_activos = "INSERT INTO activos_v2 (valor_activos, vida_util) VALUES (5000000, 10)";
    if ($conn->query($sql_insert_activos) === FALSE) {
        echo "Error insertando datos iniciales en activos_v2: " . $conn->error;
    }
}

// Insertar datos iniciales en costos fijos si no existen
$sql_check_costos = "SELECT COUNT(*) as count FROM costos_fijos_v2";
$result = $conn->query($sql_check_costos);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $sql_insert_costos = "INSERT INTO costos_fijos_v2 (numero_carros, sueldo_base, cargas_sociales, permisos, servicios, otros_fijos) 
                         VALUES (1, 500000, 25, 50000, 100000, 50000)";
    if ($conn->query($sql_insert_costos) === FALSE) {
        echo "Error insertando datos iniciales en costos_fijos_v2: " . $conn->error;
    }
}

// Insertar datos iniciales en ventas si no existen
$sql_check_ventas = "SELECT COUNT(*) as count FROM ventas_v2";
$result = $conn->query($sql_check_ventas);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $sql_insert_ventas = "INSERT INTO ventas_v2 (carro_id, precio_promedio, costo_variable, cantidad_vendida) 
                         VALUES (1, 5000, 40, 30)";
    if ($conn->query($sql_insert_ventas) === FALSE) {
        echo "Error insertando datos iniciales en ventas_v2: " . $conn->error;
    }
}

echo "Tablas v2 creadas correctamente y datos iniciales insertados.";

$conn->close();
?>