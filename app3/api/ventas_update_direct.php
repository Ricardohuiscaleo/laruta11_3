<?php
header('Content-Type: application/json');
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

// Obtener datos del POST
$carro_id = isset($_POST['carro_id']) ? intval($_POST['carro_id']) : 0;
$precio_promedio = isset($_POST['precio_promedio']) ? floatval($_POST['precio_promedio']) : 0;
$costo_variable = isset($_POST['costo_variable']) ? floatval($_POST['costo_variable']) : 0;
$cantidad_vendida = isset($_POST['cantidad_vendida']) ? intval($_POST['cantidad_vendida']) : 0;

// Obtener datos de personal
$cargo_1 = isset($_POST['cargo_1']) ? $conn->real_escape_string($_POST['cargo_1']) : 'Vendedor';
$sueldo_1 = isset($_POST['sueldo_1']) ? intval($_POST['sueldo_1']) : 500000;
$cargo_2 = isset($_POST['cargo_2']) ? $conn->real_escape_string($_POST['cargo_2']) : 'Ayudante';
$sueldo_2 = isset($_POST['sueldo_2']) ? intval($_POST['sueldo_2']) : 400000;
$cargo_3 = isset($_POST['cargo_3']) ? $conn->real_escape_string($_POST['cargo_3']) : 'NULL';
$sueldo_3 = isset($_POST['sueldo_3']) ? intval($_POST['sueldo_3']) : 'NULL';
$cargo_4 = isset($_POST['cargo_4']) ? $conn->real_escape_string($_POST['cargo_4']) : 'NULL';
$sueldo_4 = isset($_POST['sueldo_4']) ? intval($_POST['sueldo_4']) : 'NULL';

// Validar datos
if ($carro_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'ID de carro inválido'
    ]));
}

// Verificar si existe el registro
$result = $conn->query("SELECT id FROM ventas_v2 WHERE carro_id = $carro_id");

if ($result->num_rows > 0) {
    // Actualizar registro existente
    $row = $result->fetch_assoc();
    $id = $row['id'];
    
    // Construir la consulta de actualización
    $sql = "UPDATE ventas_v2 SET 
            precio_promedio = $precio_promedio, 
            costo_variable = $costo_variable, 
            cantidad_vendida = $cantidad_vendida";
    
    // Añadir campos de personal solo si están definidos
    if (isset($_POST['cargo_1'])) $sql .= ", cargo_1 = '$cargo_1'";
    if (isset($_POST['sueldo_1'])) $sql .= ", sueldo_1 = $sueldo_1";
    if (isset($_POST['cargo_2'])) $sql .= ", cargo_2 = '$cargo_2'";
    if (isset($_POST['sueldo_2'])) $sql .= ", sueldo_2 = $sueldo_2";
    if (isset($_POST['cargo_3'])) $sql .= ", cargo_3 = " . ($cargo_3 === 'NULL' ? "NULL" : "'$cargo_3'");
    if (isset($_POST['sueldo_3'])) $sql .= ", sueldo_3 = " . ($sueldo_3 === 'NULL' ? "NULL" : $sueldo_3);
    if (isset($_POST['cargo_4'])) $sql .= ", cargo_4 = " . ($cargo_4 === 'NULL' ? "NULL" : "'$cargo_4'");
    if (isset($_POST['sueldo_4'])) $sql .= ", sueldo_4 = " . ($sueldo_4 === 'NULL' ? "NULL" : $sueldo_4);
    
    $sql .= " WHERE id = $id";
} else {
    // Insertar nuevo registro
    $sql = "INSERT INTO ventas_v2 
            (carro_id, precio_promedio, costo_variable, cantidad_vendida, 
             cargo_1, sueldo_1, cargo_2, sueldo_2, 
             cargo_3, sueldo_3, cargo_4, sueldo_4) 
            VALUES (
                $carro_id, $precio_promedio, $costo_variable, $cantidad_vendida, 
                '$cargo_1', $sueldo_1, '$cargo_2', $sueldo_2, 
                " . ($cargo_3 === 'NULL' ? "NULL" : "'$cargo_3'") . ", 
                " . ($sueldo_3 === 'NULL' ? "NULL" : $sueldo_3) . ", 
                " . ($cargo_4 === 'NULL' ? "NULL" : "'$cargo_4'") . ", 
                " . ($sueldo_4 === 'NULL' ? "NULL" : $sueldo_4) . "
            )";
}

// Ejecutar la consulta
if ($conn->query($sql)) {
    // Actualizar el sueldo_base en costos_fijos_v2
    $sql_update_sueldo = "UPDATE costos_fijos_v2 SET sueldo_base = (
        SELECT SUM(COALESCE(sueldo_1, 0) + COALESCE(sueldo_2, 0) + COALESCE(sueldo_3, 0) + COALESCE(sueldo_4, 0)) 
        FROM ventas_v2
    ) ORDER BY id DESC LIMIT 1";
    
    $conn->query($sql_update_sueldo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ventas y personal actualizados correctamente',
        'sql' => $sql
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar ventas: ' . $conn->error,
        'sql' => $sql
    ]);
}

// Cerrar conexión
$conn->close();
?>