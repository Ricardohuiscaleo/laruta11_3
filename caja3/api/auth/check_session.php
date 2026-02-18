<?php
require_once __DIR__ . '/../session_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (isset($_SESSION['cashier'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => $_SESSION['cashier']
    ]);
} else {
    echo json_encode([
        'authenticated' => false
    ]);
}
?>
