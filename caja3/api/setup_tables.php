<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir archivo de configuración
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
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

// 1. Tabla de activos (foodtrucks, equipos, etc.)
$sql_activos = "CREATE TABLE IF NOT EXISTS activos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    valor_adquisicion DECIMAL(12,2) NOT NULL,
    fecha_adquisicion DATE NOT NULL,
    vida_util_anios INT NOT NULL DEFAULT 10,
    valor_residual DECIMAL(12,2) DEFAULT 0,
    estado ENUM('activo', 'inactivo', 'en_mantenimiento', 'vendido') DEFAULT 'activo',
    ubicacion VARCHAR(255),
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$results[] = executeQuery($conn, $sql_activos, "activos");

// 2. Tabla de depreciación de activos
$sql_depreciacion = "CREATE TABLE IF NOT EXISTS depreciacion_activos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activo_id INT NOT NULL,
    fecha_depreciacion DATE NOT NULL,
    monto_depreciacion DECIMAL(12,2) NOT NULL,
    valor_libro_actual DECIMAL(12,2) NOT NULL,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE
)";
$results[] = executeQuery($conn, $sql_depreciacion, "depreciacion_activos");

// 3. Tabla de empleados
$sql_empleados = "CREATE TABLE IF NOT EXISTS empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(12) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE,
    direccion VARCHAR(255),
    telefono VARCHAR(20),
    email VARCHAR(100),
    fecha_contratacion DATE NOT NULL,
    cargo VARCHAR(100) NOT NULL,
    sueldo_base DECIMAL(10,2) NOT NULL,
    afp VARCHAR(50),
    sistema_salud ENUM('FONASA', 'ISAPRE') DEFAULT 'FONASA',
    isapre_plan VARCHAR(100),
    estado ENUM('activo', 'inactivo', 'vacaciones', 'licencia') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$results[] = executeQuery($conn, $sql_empleados, "empleados");

// 4. Tabla de nóminas (liquidaciones de sueldo)
$sql_nominas = "CREATE TABLE IF NOT EXISTS nominas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    periodo_mes INT NOT NULL,
    periodo_anio INT NOT NULL,
    dias_trabajados INT NOT NULL DEFAULT 30,
    sueldo_base DECIMAL(10,2) NOT NULL,
    gratificacion DECIMAL(10,2) DEFAULT 0,
    horas_extra INT DEFAULT 0,
    monto_horas_extra DECIMAL(10,2) DEFAULT 0,
    bonos DECIMAL(10,2) DEFAULT 0,
    comisiones DECIMAL(10,2) DEFAULT 0,
    total_haberes_imponibles DECIMAL(10,2) NOT NULL,
    total_haberes_no_imponibles DECIMAL(10,2) DEFAULT 0,
    cotizacion_afp DECIMAL(10,2) NOT NULL,
    cotizacion_salud DECIMAL(10,2) NOT NULL,
    cotizacion_seguro_cesantia DECIMAL(10,2) NOT NULL,
    impuesto_renta DECIMAL(10,2) DEFAULT 0,
    anticipos DECIMAL(10,2) DEFAULT 0,
    otros_descuentos DECIMAL(10,2) DEFAULT 0,
    total_descuentos DECIMAL(10,2) NOT NULL,
    sueldo_liquido DECIMAL(10,2) NOT NULL,
    fecha_pago DATE,
    estado ENUM('pendiente', 'pagado', 'anulado') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    UNIQUE KEY (empleado_id, periodo_mes, periodo_anio)
)";
$results[] = executeQuery($conn, $sql_nominas, "nominas");

// 5. Tabla de proyecciones financieras
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

// 6. Tabla de detalles de proyección por carro
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

// 7. Tabla de impuestos mensuales
$sql_impuestos = "CREATE TABLE IF NOT EXISTS impuestos_mensuales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periodo_mes INT NOT NULL,
    periodo_anio INT NOT NULL,
    iva_debito DECIMAL(10,2) NOT NULL,
    iva_credito DECIMAL(10,2) NOT NULL,
    iva_a_pagar DECIMAL(10,2) NOT NULL,
    ventas_netas DECIMAL(12,2) NOT NULL,
    ppm_porcentaje DECIMAL(5,3) NOT NULL DEFAULT 0.25,
    ppm_monto DECIMAL(10,2) NOT NULL,
    fecha_declaracion DATE,
    estado ENUM('pendiente', 'declarado', 'pagado') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (periodo_mes, periodo_anio)
)";
$results[] = executeQuery($conn, $sql_impuestos, "impuestos_mensuales");

// Devolver resultados
echo json_encode(["results" => $results]);
?>