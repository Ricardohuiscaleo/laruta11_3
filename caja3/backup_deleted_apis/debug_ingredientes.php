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

// Obtener ID del ingrediente si se proporciona
$ingrediente_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($ingrediente_id) {
    // Buscar un ingrediente específico
    $sql = "SELECT * FROM ingredientes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ingrediente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $ingrediente = $result->fetch_assoc();
        
        // Convertir valores numéricos correctamente
        $ingrediente['id'] = intval($ingrediente['id']);
        $ingrediente['costo_compra'] = floatval($ingrediente['costo_compra']);
        $ingrediente['costo_neto'] = isset($ingrediente['costo_neto']) ? floatval($ingrediente['costo_neto']) : null;
        $ingrediente['costo_por_gramo'] = floatval($ingrediente['costo_por_gramo']);
        $ingrediente['peso'] = floatval($ingrediente['peso']);
        $ingrediente['stock'] = floatval($ingrediente['stock']);
        $ingrediente['unidad_gramos'] = floatval($ingrediente['unidad_gramos']);
        
        // Convertir valores booleanos
        $ingrediente['iva_incluido'] = (bool)$ingrediente['iva_incluido'];
        
        // Asegurar que el nombre esté disponible en ambos campos para compatibilidad
        $ingrediente['name'] = $ingrediente['nombre'];
        
        // Buscar recetas que usan este ingrediente
        $sql = "SELECT r.producto_id, p.nombre as producto_nombre, r.gramos 
                FROM recetas r 
                LEFT JOIN productos p ON r.producto_id = p.id 
                WHERE r.ingrediente_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $ingrediente_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recetas = [];
        while ($row = $result->fetch_assoc()) {
            $recetas[] = $row;
        }
        
        $ingrediente['recetas'] = $recetas;
        
        echo json_encode(['success' => true, 'ingrediente' => $ingrediente]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ingrediente no encontrado']);
    }
} else {
    // Obtener todos los ingredientes con información de uso
    $sql = "SELECT i.*, 
            (SELECT COUNT(*) FROM recetas r WHERE r.ingrediente_id = i.id) as uso_count 
            FROM ingredientes i 
            ORDER BY i.nombre";
    $result = $conn->query($sql);
    
    $ingredientes = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Convertir valores numéricos correctamente
            $row['id'] = intval($row['id']);
            $row['costo_compra'] = floatval($row['costo_compra']);
            $row['costo_neto'] = isset($row['costo_neto']) ? floatval($row['costo_neto']) : null;
            $row['costo_por_gramo'] = floatval($row['costo_por_gramo']);
            $row['peso'] = floatval($row['peso']);
            $row['stock'] = floatval($row['stock']);
            $row['unidad_gramos'] = floatval($row['unidad_gramos']);
            $row['uso_count'] = intval($row['uso_count']);
            
            // Convertir valores booleanos
            $row['iva_incluido'] = (bool)$row['iva_incluido'];
            
            // Asegurar que el nombre esté disponible en ambos campos para compatibilidad
            $row['name'] = $row['nombre'];
            
            $ingredientes[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'ingredientes' => $ingredientes]);
}

$conn->close();
?>