<?php
// API para obtener una bebida por su ID
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID no válido']);
    exit;
}

$id = intval($_GET['id']);
$query = "SELECT * FROM bebidas WHERE id = $id";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $bebida = mysqli_fetch_assoc($result);
    echo json_encode($bebida);
} else {
    echo json_encode(['error' => 'Bebida no encontrada']);
}

mysqli_close($conn);
?>