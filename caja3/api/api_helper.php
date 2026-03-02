<?php
// Configuración de errores para API
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Iniciar buffer de salida para capturar cualquier eco/warning accidental
ob_start();

/**
 * Envía una respuesta JSON limpia, eliminando cualquier salida anterior.
 */
function send_json($data)
{
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ob_end_flush();
    exit;
}

/**
 * Manejador estándar de excepciones para APIs.
 */
function handle_api_exception($e)
{
    send_json([
        'success' => false,
        'error' => $e->getMessage(),
        'type' => get_class($e)
    ]);
}set_exception_handler('handle_api_exception');