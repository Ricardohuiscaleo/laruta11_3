<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config = require __DIR__ . '/../../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->query("SELECT expires_at FROM gmail_tokens ORDER BY updated_at DESC LIMIT 1");
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token) { echo json_encode(['ok' => false]); exit; }

    echo json_encode(['ok' => time() < (int)$token['expires_at']]);
} catch (Exception $e) {
    echo json_encode(['ok' => false]);
}
