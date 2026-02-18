<?php
// Configuración centralizada de sesión PHP
// Duración: 30 días (2592000 segundos)

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