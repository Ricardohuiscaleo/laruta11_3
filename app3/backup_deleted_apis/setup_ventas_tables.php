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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL
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

// Devolver resultados
echo json_encode(["results" => $results]);

mysqli_close($conn);
?>