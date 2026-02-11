<?php
session_start();
header('Content-Type: application/json');

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($is_logged_in) {
    // Verificar si la sesión no ha expirado (24 horas)
    $login_time = $_SESSION['admin_login_time'] ?? 0;
    $current_time = time();
    $session_duration = 24 * 60 * 60; // 24 horas
    
    if (($current_time - $login_time) > $session_duration) {
        session_destroy();
        $is_logged_in = false;
    }
}

echo json_encode([
    'authenticated' => $is_logged_in,
    'user' => $is_logged_in ? ($_SESSION['admin_user'] ?? 'admin') : null
]);
?>