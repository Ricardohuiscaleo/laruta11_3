<?php
// Configuración centralizada de sesión PHP con MySQL
// Duración: 30 días (2592000 segundos)

error_reporting(0); // Suprimir warnings para evitar romper JSON
ini_set('display_errors', '0');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require $path;
        break;
    }
}

if (!$config || !is_array($config)) {
    die('Config file not found');
}

require_once __DIR__ . '/MySQLSessionHandler.php';

// Usar MySQL para almacenar sesiones
$handler = new MySQLSessionHandler($config);
session_set_save_handler($handler, true);

session_set_cookie_params([
    'lifetime' => 2592000, // 30 días
    'path' => '/',
    'domain' => '.laruta11.cl',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.gc_maxlifetime', 2592000);
session_start();
?>