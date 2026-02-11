<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$result = ["success" => true, "message" => "", "details" => []];

// Verificar si existe la tabla personal_v2
$sql_check_table = "SHOW TABLES LIKE 'personal_v2'";
$result_check_table = $conn->query($sql_check_table);

if ($result_check_table->num_rows == 0) {
    $result["success"] = false;
    $result["message"] = "La tabla personal_v2 no existe. No hay datos para migrar.";
    echo json_encode($result);
    exit;
}

// Obtener todos los carros
$sql_carros = "SELECT DISTINCT carro_id FROM personal_v2";
$result_carros = $conn->query($sql_carros);

if ($result_carros->num_rows == 0) {
    $result["success"] = false;
    $result["message"] = "No hay datos de personal para migrar.";
    echo json_encode($result);
    exit;
}

// Para cada carro, obtener sus datos de personal
while ($row_carro = $result_carros->fetch_assoc()) {
    $carro_id = $row_carro['carro_id'];
    $carro_result = ["carro_id" => $carro_id, "status" => "", "empleados" => []];
    
    // Obtener datos de personal para este carro
    $sql_personal = "SELECT * FROM personal_v2 WHERE carro_id = $carro_id ORDER BY id";
    $result_personal = $conn->query($sql_personal);
    
    if ($result_personal->num_rows == 0) {
        $carro_result["status"] = "No hay datos de personal para este carro";
        $result["details"][] = $carro_result;
        continue;
    }
    
    // Preparar los datos para actualizar la tabla ventas_v2
    $cargos = [];
    $sueldos = [];
    
    $index = 1;
    while ($row_personal = $result_personal->fetch_assoc()) {
        if ($index > 4) {
            $carro_result["status"] = "Advertencia: El carro tiene más de 4 empleados. Solo se migrarán los primeros 4.";
            break;
        }
        
        $cargos[$index] = $row_personal['cargo'];
        $sueldos[$index] = $row_personal['sueldo'];
        
        $carro_result["empleados"][] = [
            "index" => $index,
            "cargo" => $row_personal['cargo'],
            "sueldo" => $row_personal['sueldo']
        ];
        
        $index++;
    }
    
    // Actualizar la tabla ventas_v2
    $sql_update = "UPDATE ventas_v2 SET ";
    $params = [];
    
    for ($i = 1; $i <= 4; $i++) {
        if (isset($cargos[$i])) {
            $sql_update .= "cargo_$i = ?, sueldo_$i = ?, ";
            $params[] = $cargos[$i];
            $params[] = $sueldos[$i];
        } else {
            $sql_update .= "cargo_$i = NULL, sueldo_$i = NULL, ";
        }
    }
    
    // Eliminar la última coma y espacio
    $sql_update = rtrim($sql_update, ", ");
    
    $sql_update .= " WHERE carro_id = ?";
    $params[] = $carro_id;
    
    // Preparar y ejecutar la consulta
    $stmt = $conn->prepare($sql_update);
    
    if (!$stmt) {
        $carro_result["status"] = "Error al preparar la consulta: " . $conn->error;
        $result["details"][] = $carro_result;
        continue;
    }
    
    // Construir los tipos de parámetros
    $types = "";
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= "i";
        } else {
            $types .= "s";
        }
    }
    
    // Vincular los parámetros
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $carro_result["status"] = "Datos migrados correctamente";
    } else {
        $carro_result["status"] = "Error al migrar datos: " . $stmt->error;
    }
    
    $result["details"][] = $carro_result;
    $stmt->close();
}

// Actualizar el sueldo_base en costos_fijos_v2
$sql_update_sueldo = "UPDATE costos_fijos_v2 SET sueldo_base = (
    SELECT SUM(COALESCE(sueldo_1, 0) + COALESCE(sueldo_2, 0) + COALESCE(sueldo_3, 0) + COALESCE(sueldo_4, 0)) 
    FROM ventas_v2
) ORDER BY id DESC LIMIT 1";

if ($conn->query($sql_update_sueldo)) {
    $result["sueldo_base_updated"] = true;
} else {
    $result["sueldo_base_updated"] = false;
    $result["sueldo_base_error"] = $conn->error;
}

// Cerrar conexión
$conn->close();

$result["message"] = "Migración completada";
echo json_encode($result);
?>