<?php
header('Content-Type: application/json');

$token_path = '/var/www/html/api/gmail/gmail_token.json';

$result = [
    'token_path' => $token_path,
    'file_exists' => file_exists($token_path),
    'is_readable' => file_exists($token_path) ? is_readable($token_path) : false,
    'is_writable' => is_writable(dirname($token_path)),
    'directory_exists' => is_dir(dirname($token_path)),
    'directory_writable' => is_writable(dirname($token_path))
];

if (file_exists($token_path)) {
    $result['file_size'] = filesize($token_path);
    $result['file_modified'] = date('Y-m-d H:i:s', filemtime($token_path));
    $result['file_content_preview'] = substr(file_get_contents($token_path), 0, 100) . '...';
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
