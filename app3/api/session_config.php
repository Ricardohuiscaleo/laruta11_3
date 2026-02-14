<?php
// Configuración centralizada de sesión PHP
// Duración: 30 días (2592000 segundos)

ini_set('session.cookie_lifetime', 2592000);
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Crear cookie persistente simple para mantener sesión
if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
    setcookie(
        'ruta11_session',
        session_id(),
        [
            'expires' => time() + 2592000, // 30 días
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}
?>
