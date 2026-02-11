<?php
header("Content-Type: application/json");
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Actualizar costo_por_gramo para todos los ingredientes
$conn->query("UPDATE ingredientes SET costo_por_gramo = costo_compra / 1000 WHERE costo_por_gramo = 0 OR costo_por_gramo IS NULL");

// Asegurarse de que todos los ingredientes tengan un valor para costo_compra
$conn->query("UPDATE ingredientes SET costo_compra = 1000 WHERE costo_compra = 0 OR costo_compra IS NULL");

echo json_encode([
    'success' => true,
    'mensaje' => 'Ingredientes actualizados correctamente'
]);

$conn->close();
?>