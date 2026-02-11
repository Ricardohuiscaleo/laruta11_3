<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Incluir archivo de configuración
require_once __DIR__ . '/../config.php';

// Si no se pudo conectar con la configuración principal, intentar con alternativas
if (!isset($conn) || $conn === false) {
    // Si no existe, intentamos con la configuración global
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
        
        // Configurar la conexión a la base de datos usando los valores del config global
        $conn = mysqli_connect(
            $config['Calcularuta11_db_host'],
            $config['Calcularuta11_db_user'],
            $config['Calcularuta11_db_pass'],
            $config['Calcularuta11_db_name']
        );
        
        // Verificar la conexión
        if($conn === false){
            http_response_code(500);
            echo json_encode(["error" => "No se pudo conectar a la base de datos: " . mysqli_connect_error()]);
            exit;
        }
        
        // Configurar el conjunto de caracteres a utf8
        mysqli_set_charset($conn, "utf8");
    } else {
        http_response_code(500);
        echo json_encode(["error" => "No se encontró el archivo de configuración"]);
        exit;
    }
}

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

// 1. Tabla de ventas
$sql_ventas = "CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_venta DATE NOT NULL,
    hora_venta TIME NOT NULL,
    monto_total DECIMAL(12,2) NOT NULL,
    monto_neto DECIMAL(12,2) NOT NULL,
    iva DECIMAL(12,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia', 'otro') NOT NULL,
    carro_id INT NOT NULL,
    empleado_id INT,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$results[] = executeQuery($conn, $sql_ventas, "ventas");

// 2. Tabla de detalles de venta
$sql_detalles_venta = "CREATE TABLE IF NOT EXISTS detalles_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE
)";
$results[] = executeQuery($conn, $sql_detalles_venta, "detalles_venta");

// 3. Tabla de estadísticas diarias
$sql_estadisticas = "CREATE TABLE IF NOT EXISTS estadisticas_diarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    carro_id INT NOT NULL,
    total_ventas DECIMAL(12,2) NOT NULL,
    cantidad_ventas INT NOT NULL,
    ticket_promedio DECIMAL(10,2) NOT NULL,
    hora_pico TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (fecha, carro_id)
)";
$results[] = executeQuery($conn, $sql_estadisticas, "estadisticas_diarias");

// 4. Tabla de proyecciones financieras (si no existe)
$sql_proyecciones = "CREATE TABLE IF NOT EXISTS proyecciones_financieras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    periodo_mes INT NOT NULL,
    periodo_anio INT NOT NULL,
    valor_activos DECIMAL(12,2) NOT NULL,
    vida_util_anios DECIMAL(5,2) NOT NULL,
    numero_carros INT NOT NULL,
    sueldo_base DECIMAL(10,2) NOT NULL,
    cargas_sociales_porcentaje DECIMAL(5,2) NOT NULL,
    permisos_por_carro DECIMAL(10,2) NOT NULL,
    servicios_por_carro DECIMAL(10,2) NOT NULL,
    otros_fijos DECIMAL(10,2) NOT NULL,
    dias_trabajo INT NOT NULL,
    ingresos_brutos_totales DECIMAL(12,2) NOT NULL,
    ingresos_netos DECIMAL(12,2) NOT NULL,
    costo_variable_total DECIMAL(12,2) NOT NULL,
    margen_bruto DECIMAL(12,2) NOT NULL,
    costos_fijos_totales DECIMAL(12,2) NOT NULL,
    utilidad_antes_impuesto DECIMAL(12,2) NOT NULL,
    iva_a_pagar DECIMAL(12,2) NOT NULL,
    ppm DECIMAL(12,2) NOT NULL,
    flujo_caja_neto DECIMAL(12,2) NOT NULL,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$results[] = executeQuery($conn, $sql_proyecciones, "proyecciones_financieras");

// Verificar si la tabla proyecciones_financieras se creó correctamente
$check_proyecciones = mysqli_query($conn, "SHOW TABLES LIKE 'proyecciones_financieras'");
if (mysqli_num_rows($check_proyecciones) == 0) {
    $results[] = ["success" => false, "message" => "Error: No se pudo crear la tabla proyecciones_financieras"];
}

// 5. Tabla de detalles de proyección (si no existe)
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

// Verificar si la tabla detalles_proyeccion se creó correctamente
$check_detalles = mysqli_query($conn, "SHOW TABLES LIKE 'detalles_proyeccion'");
if (mysqli_num_rows($check_detalles) == 0) {
    $results[] = ["success" => false, "message" => "Error: No se pudo crear la tabla detalles_proyeccion"];
}

// Insertar datos de ejemplo si no hay datos
$sql_check_proyecciones = "SELECT COUNT(*) as count FROM proyecciones_financieras";
$result_check = mysqli_query($conn, $sql_check_proyecciones);
$row = mysqli_fetch_assoc($result_check);

if ($row['count'] == 0) {
    // Insertar proyecciones para cada mes de 2025
    $meses = 12;
    $exito = true;
    
    for ($mes = 1; $mes <= $meses; $mes++) {
        // Valores base
        $precio_promedio = 5000 + rand(0, 1000); // Entre 5000 y 6000
        $cantidad_vendida = 30 + rand(0, 10); // Entre 30 y 40
        $costo_variable_porcentaje = 35 + rand(0, 10); // Entre 35% y 45%
        
        // Calcular ingresos y costos
        $ingresos_brutos = $precio_promedio * $cantidad_vendida * 24; // 24 días al mes
        $ingresos_netos = $ingresos_brutos / 1.19; // Quitar IVA
        $costo_variable = $ingresos_brutos * ($costo_variable_porcentaje / 100);
        $margen_bruto = $ingresos_netos - $costo_variable;
        
        // Costos fijos
        $sueldo_base = 500000;
        $cargas_sociales = 25;
        $costo_personal = $sueldo_base * 2 * (1 + $cargas_sociales/100); // 2 empleados
        $permisos = 50000;
        $servicios = 100000;
        $otros_fijos = 50000;
        $costos_fijos = $costo_personal + $permisos + $servicios + $otros_fijos;
        
        // Utilidad
        $utilidad = $margen_bruto - $costos_fijos;
        $iva_pagar = $ingresos_brutos - $ingresos_netos;
        $ppm = $utilidad * 0.01; // 1% de PPM
        $flujo_caja = $utilidad - $iva_pagar - $ppm;
        
        // Insertar proyección
        $sql_insert_proyeccion = "INSERT INTO proyecciones_financieras (
            nombre, periodo_mes, periodo_anio, valor_activos, vida_util_anios, 
            numero_carros, sueldo_base, cargas_sociales_porcentaje, permisos_por_carro, 
            servicios_por_carro, otros_fijos, dias_trabajo, ingresos_brutos_totales, 
            ingresos_netos, costo_variable_total, margen_bruto, costos_fijos_totales, 
            utilidad_antes_impuesto, iva_a_pagar, ppm, flujo_caja_neto, notas
        ) VALUES (
            'Proyección $mes-2025', $mes, 2025, 5000000, 5, 
            1, $sueldo_base, $cargas_sociales, $permisos, 
            $servicios, $otros_fijos, 24, $ingresos_brutos, 
            $ingresos_netos, $costo_variable, $margen_bruto, $costos_fijos, 
            $utilidad, $iva_pagar, $ppm, $flujo_caja, 'Proyección automática para dashboard'
        )";
        
        if (mysqli_query($conn, $sql_insert_proyeccion)) {
            $proyeccion_id = mysqli_insert_id($conn);
            
            // Insertar detalle de proyección
            $sql_insert_detalle = "INSERT INTO detalles_proyeccion (
                proyeccion_id, numero_carro, precio_promedio, costo_variable_porcentaje, cantidad_vendida_dia
            ) VALUES (
                $proyeccion_id, 1, $precio_promedio, $costo_variable_porcentaje, $cantidad_vendida
            )";
            
            if (!mysqli_query($conn, $sql_insert_detalle)) {
                $exito = false;
                $results[] = ["success" => false, "message" => "Error al insertar detalle para mes $mes: " . mysqli_error($conn)];
            }
        } else {
            $exito = false;
            $results[] = ["success" => false, "message" => "Error al insertar proyección para mes $mes: " . mysqli_error($conn)];
        }
    }
    
    if ($exito) {
        $results[] = ["success" => true, "message" => "Datos de ejemplo para todos los meses de 2025 insertados correctamente"];
    }
} else {
    $results[] = ["success" => true, "message" => "Ya existen proyecciones en la base de datos"];
}

// Devolver resultados
echo json_encode(["results" => $results]);

mysqli_close($conn);
?>