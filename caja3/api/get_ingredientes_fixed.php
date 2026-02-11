<?php
// Configurar encabezados para evitar caché
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Consultar ingredientes
$sql = "SELECT * FROM ingredientes ORDER BY nombre";
$result = $conn->query($sql);

$ingredientes = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Asegurar que el ID sea un número
        $row['id'] = intval($row['id']);
        
        // Convertir valores numéricos correctamente
        $row['costo_compra'] = floatval($row['costo_compra']);
        $row['costo_neto'] = isset($row['costo_neto']) ? floatval($row['costo_neto']) : null;
        $row['costo_por_gramo'] = floatval($row['costo_por_gramo']);
        $row['peso'] = floatval($row['peso']);
        $row['stock'] = floatval($row['stock']);
        $row['unidad_gramos'] = floatval($row['unidad_gramos']);
        
        // Convertir valores booleanos
        $row['iva_incluido'] = (bool)$row['iva_incluido'];
        
        // Asegurar que el nombre esté disponible en ambos campos para compatibilidad
        $row['name'] = $row['nombre'];
        
        $ingredientes[] = $row;
    }
}

echo json_encode($ingredientes);

$conn->close();
?>