<?php
// Cargar configuración desde raíz
$config = require_once __DIR__ . '/../config.php';

// Configuración de la base de datos desde config central
define('DB_SERVER', $config['db_host']);
define('DB_USERNAME', $config['db_user']);
define('DB_PASSWORD', $config['db_pass']);
define('DB_NAME', $config['db_name']);

// Definiciones adicionales para Ruta11 específicas
define('RUTA11_DB_SERVER', $config['ruta11_db_host']);
define('RUTA11_DB_USERNAME', $config['ruta11_db_user']);
define('RUTA11_DB_PASSWORD', $config['ruta11_db_pass']);
define('RUTA11_DB_NAME', $config['ruta11_db_name']);

// Intentar conectar a la base de datos MySQL
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexión
if($conn === false){
    die("ERROR: No se pudo conectar. " . mysqli_connect_error());
}

// Configurar el conjunto de caracteres a utf8
mysqli_set_charset($conn, "utf8");

// Función para mostrar mensajes de error o éxito
function mostrar_mensaje($mensaje, $tipo = 'info') {
    $clase = ($tipo == 'error') ? 'error' : (($tipo == 'success') ? 'success' : 'info');
    return "<div class='mensaje $clase'>$mensaje</div>";
}
?>