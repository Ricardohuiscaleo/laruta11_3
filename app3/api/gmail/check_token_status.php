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

    $stmt = $pdo->query("SELECT access_token, updated_at FROM gmail_tokens ORDER BY updated_at DESC LIMIT 1");
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$token) { echo json_encode(['ok' => false, 'error' => 'no_token']); exit; }

    $minutes = (time() - strtotime($token['updated_at'])) / 60;
    if ($minutes > 60) { echo json_encode(['ok' => false, 'error' => 'expired']); exit; }

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/profile');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token']],
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode(['ok' => $http_code === 200]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'db']);
}
