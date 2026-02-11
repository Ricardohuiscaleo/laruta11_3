<?php
session_start();
header('Content-Type: application/json');

// Eliminar datos de sesión del tracker
unset($_SESSION['tracker_user']);

echo json_encode(['success' => true]);
?>