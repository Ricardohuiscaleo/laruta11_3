<?php
session_start();

if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
} else {
    $_SESSION['test_counter']++;
}

$_SESSION['test_data'] = 'Funciona';

header('Content-Type: application/json');
echo json_encode([
    'counter' => $_SESSION['test_counter'],
    'test_data' => $_SESSION['test_data'],
    'session_id' => session_id(),
    'all_session' => $_SESSION
]);
?>