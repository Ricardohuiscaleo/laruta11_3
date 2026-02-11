<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Verificar que se recibió el ID
if (!isset($_POST['id'])) {
    die(json_encode([
        'success' => false,
        'message' => 'ID no proporcionado'
    ]));
}

$id = intval($_POST['id']);

// Eliminar proyección
$sql = "DELETE FROM proyecciones_v2 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Proyección eliminada correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró la proyección'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar proyección: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>