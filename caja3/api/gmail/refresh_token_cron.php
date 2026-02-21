<?php
header('Content-Type: application/json');

require_once __DIR__ . '/get_token_db.php';

$token_result = getValidGmailToken();

if (isset($token_result['error'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $token_result['error'], 'time' => date('Y-m-d H:i:s')]);
    exit(1);
}

echo json_encode([
    'success' => true,
    'message' => 'Token refrescado exitosamente',
    'token_preview' => substr($token_result['access_token'], 0, 20) . '...',
    'time' => date('Y-m-d H:i:s')
]);
