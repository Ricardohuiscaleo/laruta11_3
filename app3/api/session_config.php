<?php
// Configuración centralizada de sesión PHP
// Duración: 30 días (2592000 segundos)

ini_set('session.cookie_lifetime', 2592000);
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

session_start();
?>
