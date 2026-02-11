<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    echo json_encode(['authenticated' => true, 'user' => $_SESSION['user']]);
} else {
    echo json_encode(['authenticated' => false]);
}
?>