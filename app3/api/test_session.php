<?php
// Test de persistencia de sesión
$session_path = __DIR__ . '/../sessions';
if (!file_exists($session_path)) {
    mkdir($session_path, 0700, true);
}
session_save_path($session_path);

session_set_cookie_params([
    'lifetime' => 2592000,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.gc_maxlifetime', 2592000);
session_start();

header('Content-Type: application/json');

// Incrementar contador
if (!isset($_SESSION['counter'])) {
    $_SESSION['counter'] = 1;
} else {
    $_SESSION['counter']++;
}

echo json_encode([
    'session_id' => session_id(),
    'counter' => $_SESSION['counter'],
    'session_save_path' => session_save_path(),
    'cookie_params' => session_get_cookie_params()
]);
?>
