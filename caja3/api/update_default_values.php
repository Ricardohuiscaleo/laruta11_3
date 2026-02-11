<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Incluir archivo de configuración
require_once __DIR__ . '/../config.php';

// Si no se pudo conectar con la configuración principal
if (!isset($conn) || $conn === false) {
    http_response_code(500);
    echo json_encode(["error" => "No se encontró el archivo de configuración"]);
    exit;
}

// Valores predeterminados a actualizar
$numero_carros_default = 1; // Cambiar de 2 a 1
$sueldo_base_default = 500000; // Ajustar a 500.000
$permisos_por_carro_default = 50000; // Ajustar a 50.000

// Actualizar valores predeterminados en la tabla proyecciones_financieras
$sql_update = "ALTER TABLE proyecciones_financieras 
               ALTER COLUMN numero_carros SET DEFAULT $numero_carros_default,
               ALTER COLUMN sueldo_base SET DEFAULT $sueldo_base_default,
               ALTER COLUMN permisos_por_carro SET DEFAULT $permisos_por_carro_default";

// Ejecutar la consulta
if (mysqli_query($conn, $sql_update)) {
    echo json_encode([
        "success" => true,
        "message" => "Valores predeterminados actualizados correctamente",
        "updated_values" => [
            "numero_carros" => $numero_carros_default,
            "sueldo_base" => $sueldo_base_default,
            "permisos_por_carro" => $permisos_por_carro_default
        ]
    ]);
} else {
    // Si hay un error, verificar si es por la sintaxis de ALTER COLUMN
    // MySQL puede requerir MODIFY COLUMN en lugar de ALTER COLUMN
    $sql_update_alt = "ALTER TABLE proyecciones_financieras 
                      MODIFY COLUMN numero_carros INT NOT NULL DEFAULT $numero_carros_default,
                      MODIFY COLUMN sueldo_base DECIMAL(10,2) NOT NULL DEFAULT $sueldo_base_default,
                      MODIFY COLUMN permisos_por_carro DECIMAL(10,2) NOT NULL DEFAULT $permisos_por_carro_default";
    
    if (mysqli_query($conn, $sql_update_alt)) {
        echo json_encode([
            "success" => true,
            "message" => "Valores predeterminados actualizados correctamente (usando MODIFY COLUMN)",
            "updated_values" => [
                "numero_carros" => $numero_carros_default,
                "sueldo_base" => $sueldo_base_default,
                "permisos_por_carro" => $permisos_por_carro_default
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => "Error al actualizar los valores predeterminados: " . mysqli_error($conn)
        ]);
    }
}

// Cerrar la conexión
mysqli_close($conn);
?>