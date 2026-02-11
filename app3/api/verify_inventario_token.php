<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';

$tokens_file = __DIR__ . '/tokens.json';
if (!file_exists($tokens_file)) {
    echo json_encode(['valid' => false]);
    exit;
}

$tokens = json_decode(file_get_contents($tokens_file), true);

if (isset($tokens[$token]) && $tokens[$token]['expires'] > time()) {
    echo json_encode([
        'valid' => true,
        'username' => $tokens[$token]['username']
    ]);
} else {
    // Limpiar token expirado
    if (isset($tokens[$token])) {
        unset($tokens[$token]);
        file_put_contents($tokens_file, json_encode($tokens));
    }
    
    echo json_encode(['valid' => false]);
}
?>