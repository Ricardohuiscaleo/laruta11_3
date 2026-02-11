<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Información de diagnóstico
$info = [
    "success" => true,
    "message" => "Diagnóstico de conexión a la base de datos",
    "time" => date('Y-m-d H:i:s'),
    "config_file" => []
];

// Verificar si existe el archivo de configuración
$config_path = __DIR__ . '/../config.php';
if (file_exists($config_path)) {
    $info["config_file"]["exists"] = true;
    $info["config_file"]["path"] = realpath($config_path);
    
    // Intentar incluir el archivo de configuración
    try {
        require_once $config_path;
        $info["config_file"]["included"] = true;
        
        // Verificar si las constantes están definidas
        $info["constants"] = [
            "DB_SERVER" => defined('DB_SERVER') ? "definido" : "no definido",
            "DB_USERNAME" => defined('DB_USERNAME') ? "definido" : "no definido",
            "DB_PASSWORD" => defined('DB_PASSWORD') ? "definido" : "no definido",
            "DB_NAME" => defined('DB_NAME') ? "definido" : "no definido"
        ];
        
        // Verificar la conexión
        if (isset($conn)) {
            $info["connection"] = [
                "status" => "success",
                "message" => "Conexión establecida correctamente"
            ];
            
            // Probar una consulta simple
            $result = mysqli_query($conn, "SELECT 1");
            if ($result) {
                $info["query_test"] = [
                    "status" => "success",
                    "message" => "Consulta de prueba exitosa"
                ];
            } else {
                $info["query_test"] = [
                    "status" => "error",
                    "message" => "Error en la consulta de prueba: " . mysqli_error($conn)
                ];
            }
        } else {
            $info["connection"] = [
                "status" => "error",
                "message" => "Variable \$conn no disponible"
            ];
            
            // Intentar crear una conexión manualmente
            $info["manual_connection"] = [
                "attempt" => true
            ];
            
            if (defined('DB_SERVER') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_NAME')) {
                $manual_conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                
                if ($manual_conn) {
                    $info["manual_connection"]["status"] = "success";
                    $info["manual_connection"]["message"] = "Conexión manual exitosa";
                    mysqli_close($manual_conn);
                } else {
                    $info["manual_connection"]["status"] = "error";
                    $info["manual_connection"]["message"] = "Error en conexión manual: " . mysqli_connect_error();
                }
            } else {
                $info["manual_connection"]["status"] = "error";
                $info["manual_connection"]["message"] = "No se pueden crear conexiones manuales porque faltan constantes";
            }
        }
    } catch (Exception $e) {
        $info["config_file"]["included"] = false;
        $info["config_file"]["error"] = $e->getMessage();
    }
} else {
    $info["config_file"]["exists"] = false;
}

// Devolver información
echo json_encode($info, JSON_PRETTY_PRINT);
?>