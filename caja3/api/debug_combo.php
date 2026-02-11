<?php
require_once 'config.php';

// Debug del JSON recibido
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);

$data = json_decode($input, true);
error_log("Decoded data: " . print_r($data, true));

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON Error: " . json_last_error_msg());
}

echo json_encode(['debug' => 'Check error log']);
?>