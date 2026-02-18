<?php
$config = require __DIR__ . '/../config.php';

header('Content-Type: application/json');

echo json_encode([
    'caja_users' => array_keys($config['caja_users'] ?? []),
    'admin_users' => array_keys($config['admin_users'] ?? [])
], JSON_PRETTY_PRINT);
?>
