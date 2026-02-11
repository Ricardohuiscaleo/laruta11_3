<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener todos los ingredientes
$sql = "SELECT id, nombre, costo_compra, costo_por_gramo, unidad_nombre, unidad_gramos FROM ingredientes";
$result = $conn->query($sql);

$actualizados = 0;
$errores = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $costoCompra = floatval($row['costo_compra']);
        $costoPorGramo = floatval($row['costo_por_gramo']);
        $unidadGramos = floatval($row['unidad_gramos']);
        
        // Calcular el peso basado en el costo_compra y costo_por_gramo
        // Si costo_por_gramo es 0 o muy pequeño, usar unidad_gramos como peso
        if ($costoPorGramo > 0.01) {
            $peso = $costoCompra / $costoPorGramo;
        } else {
            $peso = $unidadGramos;
        }
        
        // Actualizar el peso en la base de datos
        $updateSql = "UPDATE ingredientes SET peso = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("di", $peso, $id);
        
        if ($stmt->execute()) {
            $actualizados++;
        } else {
            $errores[] = "Error al actualizar ingrediente ID {$id}: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Mostrar resultados
echo json_encode([
    'success' => true,
    'actualizados' => $actualizados,
    'errores' => $errores,
    'mensaje' => "Se actualizaron {$actualizados} ingredientes con valores de peso"
]);

$conn->close();
?>