<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar config
$config_path = __DIR__ . '/../../../../../config.php';
echo "Config path: " . $config_path . "<br>";
echo "Config exists: " . (file_exists($config_path) ? 'YES' : 'NO') . "<br>";

if (file_exists($config_path)) {
    $config = require_once $config_path;
    echo "Google Client ID: " . (isset($config['ruta11_google_client_id']) ? 'SET' : 'NOT SET') . "<br>";
    echo "Google Client Secret: " . (isset($config['ruta11_google_client_secret']) ? 'SET' : 'NOT SET') . "<br>";
    echo "DB Config: " . (isset($config['ruta11_db_host']) ? 'SET' : 'NOT SET') . "<br>";
}

echo "GET code: " . (isset($_GET['code']) ? 'YES' : 'NO') . "<br>";
if (isset($_GET['code'])) {
    echo "Code: " . substr($_GET['code'], 0, 20) . "...<br>";
}

// Test DB connection
if (isset($config)) {
    $conn = mysqli_connect(
        $config['ruta11_db_host'],
        $config['ruta11_db_user'],
        $config['ruta11_db_pass'],
        $config['ruta11_db_name']
    );
    
    echo "DB Connection: " . ($conn ? 'SUCCESS' : 'FAILED') . "<br>";
    if ($conn) {
        echo "DB Error: " . mysqli_connect_error() . "<br>";
        mysqli_close($conn);
    }
}
?>