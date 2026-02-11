<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Incluir archivo de configuración
require_once '../config.php';

// Función para ejecutar consultas SQL y manejar errores
function executeQuery($conn, $sql, $tableName) {
    if (mysqli_query($conn, $sql)) {
        return ["success" => true, "message" => "Tabla '$tableName' creada o ya existente"];
    } else {
        return ["success" => false, "message" => "Error al crear la tabla '$tableName': " . mysqli_error($conn)];
    }
}

// Array para almacenar resultados
$results = [];

// 1. Tabla de proyecciones financieras
$sql_proyecciones = "CREATE TABLE IF NOT EXISTS proyecciones_financieras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    fecha_creacion DATE NOT NULL,
    periodo_mes INT NOT NULL,
    periodo_anio INT NOT NULL,
    valor_activos DECIMAL(12,2) NOT NULL,
    vida_util_anios INT NOT NULL DEFAULT 10,
    numero_carros INT NOT NULL DEFAULT 1,
    sueldo_base DECIMAL(10,2) NOT NULL,
    cargas_sociales_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 25.00,
    permisos_por_carro DECIMAL(10,2) NOT NULL,
    servicios_por_carro DECIMAL(10,2) NOT NULL,
    otros_fijos DECIMAL(10,2) DEFAULT 0,
    dias_trabajo INT NOT NULL DEFAULT 24,
    ingresos_brutos_totales DECIMAL(12,2) NOT NULL,
    ingresos_netos DECIMAL(12,2) NOT NULL,
    costo_variable_total DECIMAL(12,2) NOT NULL,
    margen_bruto DECIMAL(12,2) NOT NULL,
    costos_fijos_totales DECIMAL(12,2) NOT NULL,
    utilidad_antes_impuesto DECIMAL(12,2) NOT NULL,
    iva_a_pagar DECIMAL(10,2) NOT NULL,
    ppm DECIMAL(10,2) NOT NULL,
    flujo_caja_neto DECIMAL(12,2) NOT NULL,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$results[] = executeQuery($conn, $sql_proyecciones, "proyecciones_financieras");

// 2. Tabla de detalles de proyección por carro
$sql_detalles_proyeccion = "CREATE TABLE IF NOT EXISTS detalles_proyeccion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyeccion_id INT NOT NULL,
    numero_carro INT NOT NULL,
    precio_promedio DECIMAL(10,2) NOT NULL,
    costo_variable_porcentaje DECIMAL(5,2) NOT NULL,
    cantidad_vendida_dia INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyeccion_id) REFERENCES proyecciones_financieras(id) ON DELETE CASCADE
)";
$results[] = executeQuery($conn, $sql_detalles_proyeccion, "detalles_proyeccion");

// Verificar si hay datos de ejemplo
$sql_check = "SELECT COUNT(*) as count FROM proyecciones_financieras";
$result_check = mysqli_query($conn, $sql_check);
$row = mysqli_fetch_assoc($result_check);

if ($row['count'] == 0) {
    // Insertar un ejemplo básico
    $fecha_actual = date('Y-m-d');
    $mes_actual = date('n');
    $anio_actual = date('Y');
    
    $sql_insert = "INSERT INTO proyecciones_financieras (
        nombre, fecha_creacion, periodo_mes, periodo_anio, valor_activos,
        vida_util_anios, numero_carros, sueldo_base, cargas_sociales_porcentaje,
        permisos_por_carro, servicios_por_carro, otros_fijos, dias_trabajo,
        ingresos_brutos_totales, ingresos_netos, costo_variable_total,
        margen_bruto, costos_fijos_totales, utilidad_antes_impuesto,
        iva_a_pagar, ppm, flujo_caja_neto, notas
    ) VALUES (
        'Proyección Ejemplo', '$fecha_actual', $mes_actual, $anio_actual, 25000000,
        10, 2, 500000, 25,
        150000, 100000, 50000, 24,
        16800000, 14117647, 6352941,
        7764706, 2583333, 5181373,
        2682353, 35294, 2463726, 'Proyección de ejemplo creada automáticamente'
    )";
    
    if (mysqli_query($conn, $sql_insert)) {
        $proyeccion_id = mysqli_insert_id($conn);
        $results[] = ["success" => true, "message" => "Proyección de ejemplo creada con ID: $proyeccion_id"];
        
        // Insertar detalles para dos carros
        $sql_detalle1 = "INSERT INTO detalles_proyeccion (
            proyeccion_id, numero_carro, precio_promedio, costo_variable_porcentaje, cantidad_vendida_dia
        ) VALUES (
            $proyeccion_id, 1, 7000, 45, 50
        )";
        
        $sql_detalle2 = "INSERT INTO detalles_proyeccion (
            proyeccion_id, numero_carro, precio_promedio, costo_variable_porcentaje, cantidad_vendida_dia
        ) VALUES (
            $proyeccion_id, 2, 7000, 45, 50
        )";
        
        mysqli_query($conn, $sql_detalle1);
        mysqli_query($conn, $sql_detalle2);
        
        $results[] = ["success" => true, "message" => "Detalles de ejemplo creados para la proyección"];
    } else {
        $results[] = ["success" => false, "message" => "Error al crear proyección de ejemplo: " . mysqli_error($conn)];
    }
} else {
    $results[] = ["success" => true, "message" => "Ya existen proyecciones en la base de datos"];
}

// Devolver resultados
echo json_encode(["results" => $results]);

mysqli_close($conn);
?>