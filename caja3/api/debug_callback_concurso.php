<?php
// Debug callback simple
$logFile = __DIR__ . '/callback_debug.log';
$timestamp = date('Y-m-d H:i:s');

$logData = [
    'timestamp' => $timestamp,
    'method' => $_SERVER['REQUEST_METHOD'],
    'get_params' => $_GET,
    'post_params' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'headers' => getallheaders(),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
];

file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Redirigir a página de gracias con parámetros de debug
$params = http_build_query([
    'debug' => 'callback_executed',
    'timestamp' => $timestamp,
    'method' => $_SERVER['REQUEST_METHOD'],
    'params_count' => count($_GET)
]);

header("Location: https://app.laruta11.cl/concurso/gracias/?$params");
exit;
?>