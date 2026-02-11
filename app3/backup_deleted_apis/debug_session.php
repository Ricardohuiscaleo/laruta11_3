<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_data' => $_SESSION,
    'session_id' => session_id(),
    'has_user_id' => isset($_SESSION['user_id']),
    'has_auth_type' => isset($_SESSION['auth_type']),
    'auth_type' => $_SESSION['auth_type'] ?? null,
    'has_jobs_user_id' => isset($_SESSION['jobs_user_id'])
]);
?>