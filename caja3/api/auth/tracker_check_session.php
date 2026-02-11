<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    if (isset($_SESSION['tracker_user'])) {
        echo json_encode([
            'authenticated' => true,
            'user' => $_SESSION['tracker_user']
        ]);
    } else {
        echo json_encode([
            'authenticated' => false
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => 'Server error'
    ]);
}
?>