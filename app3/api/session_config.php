<?php
// Configuración centralizada de sesión PHP con MySQL
// Duración: 30 días (2592000 segundos)

$config_path = __DIR__ . '/../config.php';
if (!file_exists($config_path)) {
    die('Config file not found');
}
$config = require $config_path;

require_once __DIR__ . '/MySQLSessionHandler.php';

// Usar MySQL para almacenar sesiones
$handler = new MySQLSessionHandler($config);
session_set_save_handler($handler, true);

session_set_cookie_params([
    'lifetime' => 2592000, // 30 días
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.gc_maxlifetime', 2592000);
session_start();
?>