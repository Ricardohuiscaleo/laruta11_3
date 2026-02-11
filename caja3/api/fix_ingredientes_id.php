<?php
header("Content-Type: application/json");
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener todos los ingredientes
$result = $conn->query("SELECT id, nombre FROM ingredientes");
$ingredientes = [];
while ($row = $result->fetch_assoc()) {
    $ingredientes[$row['id']] = $row['nombre'];
}

// Actualizar el campo costo_por_gramo para todos los ingredientes
$conn->query("UPDATE ingredientes SET costo_por_gramo = costo_compra / 1000 WHERE costo_por_gramo = 0");

// Obtener todas las recetas
$result = $conn->query("SELECT id, producto_id, ingrediente_id, gramos FROM recetas");
$recetas_actualizadas = 0;

while ($row = $result->fetch_assoc()) {
    $receta_id = $row['id'];
    $ingrediente_id = $row['ingrediente_id'];
    
    // Verificar si el ingrediente existe
    if (!isset($ingredientes[$ingrediente_id])) {
        // Buscar un ingrediente existente para reemplazar
        $nuevo_id = array_key_first($ingredientes);
        
        // Actualizar la receta
        $stmt = $conn->prepare("UPDATE recetas SET ingrediente_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $nuevo_id, $receta_id);
        
        if ($stmt->execute()) {
            $recetas_actualizadas++;
        }
        
        $stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'recetas_actualizadas' => $recetas_actualizadas,
    'mensaje' => 'Ingredientes actualizados correctamente'
]);

$conn->close();
?>