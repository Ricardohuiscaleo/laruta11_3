<?php
require_once __DIR__ . '/../session_config.php';

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://app.laruta11.cl';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (isset($_SESSION['cashier'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => $_SESSION['cashier']
    ]);
}
else {
    echo json_encode([
        'authenticated' => false
    ]);
}
?>