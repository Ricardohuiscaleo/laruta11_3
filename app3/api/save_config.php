<?php
// API para guardar la configuración
header('Content-Type: application/json');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

// Obtener los datos enviados
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['iva_rate'])) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

$iva_rate = floatval($data['iva_rate']);

if ($iva_rate <= 0) {
    echo json_encode(['error' => 'La tasa de IVA debe ser mayor que cero']);
    exit;
}

// Verificar si existe la tabla de configuración
$query = "SHOW TABLES LIKE 'configuracion'";
$result = mysqli_query($conn, $query);
$tabla_existe = $result && mysqli_num_rows($result) > 0;

if (!$tabla_existe) {
    // Crear la tabla de configuración
    $query = "CREATE TABLE configuracion (
        id INT(11) NOT NULL AUTO_INCREMENT,
        clave VARCHAR(50) NOT NULL,
        valor VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (clave)
    )";
    
    if (!mysqli_query($conn, $query)) {
        echo json_encode(['error' => 'Error al crear la tabla de configuración: ' . mysqli_error($conn)]);
        exit;
    }
}

// Guardar la tasa de IVA
$query = "INSERT INTO configuracion (clave, valor) VALUES ('iva_rate', '$iva_rate')
          ON DUPLICATE KEY UPDATE valor = '$iva_rate'";

if (mysqli_query($conn, $query)) {
    echo json_encode([
        'success' => true,
        'message' => 'Configuración guardada correctamente'
    ]);
} else {
    echo json_encode([
        'error' => 'Error al guardar la configuración: ' . mysqli_error($conn)
    ]);
}
?>
