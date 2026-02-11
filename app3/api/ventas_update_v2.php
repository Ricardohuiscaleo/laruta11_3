<?php
// Evitar el almacenamiento en caché de las respuestas
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
require_once '../config.php';

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Verificar que se recibieron los datos necesarios
if (!isset($_POST['carro_id']) || !isset($_POST['precio_promedio']) || 
    !isset($_POST['costo_variable']) || !isset($_POST['cantidad_vendida'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Datos incompletos'
    ]));
}

// Verificar si existe la tabla personal_v2, si no, crearla
$sql_check_table = "SHOW TABLES LIKE 'personal_v2'";
$result_check_table = $conn->query($sql_check_table);

if ($result_check_table->num_rows == 0) {
    // La tabla no existe, crearla
    $sql_create_table = "CREATE TABLE personal_v2 (
        id INT(11) NOT NULL AUTO_INCREMENT,
        carro_id INT(11) NOT NULL,
        cargo VARCHAR(100) NOT NULL,
        sueldo DECIMAL(10,2) NOT NULL,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )";
    
    if (!$conn->query($sql_create_table)) {
        die(json_encode([
            'success' => false,
            'message' => 'Error al crear tabla personal_v2: ' . $conn->error
        ]));
    }
}

$carro_id = intval($_POST['carro_id']);
$precio_promedio = floatval($_POST['precio_promedio']);
$costo_variable = floatval($_POST['costo_variable']);
$cantidad_vendida = intval($_POST['cantidad_vendida']);

// Procesar datos de personal si se proporcionaron
$personal = [];
if (isset($_POST['personal'])) {
    $personal = json_decode($_POST['personal'], true);
    if (!is_array($personal)) {
        $personal = [];
    }
}

// Validar datos
if ($carro_id <= 0 || $precio_promedio < 0 || $costo_variable < 0 || $cantidad_vendida < 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Valores inválidos'
    ]));
}

// No usar valores por defecto hardcodeados
$cargo_1_default = '';
$sueldo_1_default = 0;
$cargo_2_default = '';
$sueldo_2_default = 0;
$cargo_3_default = null;
$sueldo_3_default = null;
$cargo_4_default = null;
$sueldo_4_default = null;

// Verificar si ya existe un registro para este carro
$sql_check = "SELECT * FROM ventas_v2 WHERE carro_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $carro_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows > 0) {
    // Actualizar registro existente
    $row = $result->fetch_assoc();
    $id = $row['id'];
    
    // Obtener los valores actuales o usar los nuevos si se proporcionan
    $cargo_1 = isset($_POST['cargo_1']) ? $_POST['cargo_1'] : $row['cargo_1'];
    $sueldo_1 = isset($_POST['sueldo_1']) ? floatval($_POST['sueldo_1']) : $row['sueldo_1'];
    $cargo_2 = isset($_POST['cargo_2']) ? $_POST['cargo_2'] : $row['cargo_2'];
    $sueldo_2 = isset($_POST['sueldo_2']) ? floatval($_POST['sueldo_2']) : $row['sueldo_2'];
    $cargo_3 = isset($_POST['cargo_3']) ? $_POST['cargo_3'] : $row['cargo_3'];
    $sueldo_3 = isset($_POST['sueldo_3']) ? floatval($_POST['sueldo_3']) : $row['sueldo_3'];
    $cargo_4 = isset($_POST['cargo_4']) ? $_POST['cargo_4'] : $row['cargo_4'];
    $sueldo_4 = isset($_POST['sueldo_4']) ? floatval($_POST['sueldo_4']) : $row['sueldo_4'];
    
    // Corregir los tipos de datos según la estructura de la tabla
    $sql = "UPDATE ventas_v2 SET 
            precio_promedio = ?, 
            costo_variable = ?, 
            cantidad_vendida = ?,
            cargo_1 = ?,
            sueldo_1 = ?,
            cargo_2 = ?,
            sueldo_2 = ?,
            cargo_3 = ?,
            sueldo_3 = ?,
            cargo_4 = ?,
            sueldo_4 = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddisisisisi", $precio_promedio, $costo_variable, $cantidad_vendida, 
                      $cargo_1, $sueldo_1, $cargo_2, $sueldo_2, $cargo_3, $sueldo_3, $cargo_4, $sueldo_4, $id);
} else {
    // Es un nuevo registro, usar valores por defecto
    $cargo_1 = isset($_POST['cargo_1']) ? $_POST['cargo_1'] : $cargo_1_default;
    $sueldo_1 = isset($_POST['sueldo_1']) ? floatval($_POST['sueldo_1']) : $sueldo_1_default;
    $cargo_2 = isset($_POST['cargo_2']) ? $_POST['cargo_2'] : $cargo_2_default;
    $sueldo_2 = isset($_POST['sueldo_2']) ? floatval($_POST['sueldo_2']) : $sueldo_2_default;
    $cargo_3 = isset($_POST['cargo_3']) ? $_POST['cargo_3'] : $cargo_3_default;
    $sueldo_3 = isset($_POST['sueldo_3']) ? floatval($_POST['sueldo_3']) : $sueldo_3_default;
    $cargo_4 = isset($_POST['cargo_4']) ? $_POST['cargo_4'] : $cargo_4_default;
    $sueldo_4 = isset($_POST['sueldo_4']) ? floatval($_POST['sueldo_4']) : $sueldo_4_default;
    
    // Insertar nuevo registro
    $sql = "INSERT INTO ventas_v2 
            (carro_id, precio_promedio, costo_variable, cantidad_vendida, cargo_1, sueldo_1, cargo_2, sueldo_2, cargo_3, sueldo_3, cargo_4, sueldo_4) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iddisissisis", $carro_id, $precio_promedio, $costo_variable, $cantidad_vendida, 
                      $cargo_1, $sueldo_1, $cargo_2, $sueldo_2, $cargo_3, $sueldo_3, $cargo_4, $sueldo_4);
}

// Para depuración
$debug = [
    'carro_id' => $carro_id,
    'precio_promedio' => $precio_promedio,
    'costo_variable' => $costo_variable,
    'cantidad_vendida' => $cantidad_vendida,
    'cargo_1' => $cargo_1,
    'sueldo_1' => $sueldo_1,
    'cargo_2' => $cargo_2,
    'sueldo_2' => $sueldo_2,
    'cargo_3' => $cargo_3,
    'sueldo_3' => $sueldo_3,
    'cargo_4' => $cargo_4,
    'sueldo_4' => $sueldo_4,
    'post_data' => $_POST,
    'sql' => $sql
];

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($stmt->execute()) {
    // Calcular el sueldo total para este carro
    $sueldo_total = $sueldo_1 + ($sueldo_2 ?: 0) + ($sueldo_3 ?: 0) + ($sueldo_4 ?: 0);
    
    // Actualizar el sueldo_base en costos_fijos_v2 sumando todos los sueldos de todos los carros
    $sql_update_sueldo = "UPDATE costos_fijos_v2 SET sueldo_base = (
        SELECT SUM(COALESCE(sueldo_1, 0) + COALESCE(sueldo_2, 0) + COALESCE(sueldo_3, 0) + COALESCE(sueldo_4, 0)) 
        FROM ventas_v2
    ) ORDER BY id DESC LIMIT 1";
    $conn->query($sql_update_sueldo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ventas y personal actualizados correctamente',
        'debug' => $debug
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar ventas: ' . $stmt->error,
        'debug' => $debug
    ]);
}

$stmt->close();
$stmt_check->close();
$conn->close();
?>