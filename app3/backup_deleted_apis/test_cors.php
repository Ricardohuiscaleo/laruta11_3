<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir archivo de configuración
// Primero intentamos con la configuración local
$conn = null;
$config_source = 'none';

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    $config_source = 'local';
} else {
    // Si no existe, intentamos con la configuración global
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
        $config_source = 'global';
        
        // Configurar la conexión a la base de datos usando los valores del config global
        $conn = mysqli_connect(
            $config['Calcularuta11_db_host'],
            $config['Calcularuta11_db_user'],
            $config['Calcularuta11_db_pass'],
            $config['Calcularuta11_db_name']
        );
        
        // Verificar la conexión
        if($conn === false){
            $db_status = "Error: " . mysqli_connect_error();
        } else {
            $db_status = "Conectado";
            mysqli_set_charset($conn, "utf8");
        }
    } else {
        $db_status = "No se encontró el archivo de configuración";
    }
}

// Responder con un mensaje simple para probar CORS
echo json_encode([
    "success" => true,
    "message" => "CORS está configurado correctamente",
    "timestamp" => time(),
    "config_source" => $config_source,
    "db_status" => $db_status ?? "Usando configuración local",
    "server_info" => [
        "php_version" => phpversion(),
        "server_software" => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        "request_method" => $_SERVER['REQUEST_METHOD'],
        "request_uri" => $_SERVER['REQUEST_URI'],
        "http_origin" => $_SERVER['HTTP_ORIGIN'] ?? 'Not provided',
        "script_filename" => $_SERVER['SCRIPT_FILENAME'],
        "document_root" => $_SERVER['DOCUMENT_ROOT']
    ]
]);

if ($conn) {
    $conn->close();
}
?>