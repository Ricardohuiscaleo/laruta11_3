<?php
// Configuración centralizada de sesión PHP
// Duración: 30 días (2592000 segundos)

// Configurar directorio de sesiones
$session_path = __DIR__ . '/../sessions';
if (!file_exists($session_path)) {
    mkdir($session_path, 0700, true);
}
session_save_path($session_path);

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