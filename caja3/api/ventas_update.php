<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

// Validar datos
if (!isset($data['numero_carro']) || 
    !isset($data['precio_promedio']) || 
    !isset($data['costo_variable_porcentaje']) || 
    !isset($data['cantidad_vendida_dia'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$numeroCarro = intval($data['numero_carro']);
$precioPromedio = floatval($data['precio_promedio']);
$costoVariablePorcentaje = floatval($data['costo_variable_porcentaje']);
$cantidadVendidaDia = intval($data['cantidad_vendida_dia']);

// Validar valores
if ($numeroCarro <= 0 || $precioPromedio <= 0 || $costoVariablePorcentaje < 0 || $cantidadVendidaDia < 0) {
    echo json_encode(['success' => false, 'message' => 'Valores inválidos']);
    exit;
}

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si existe una configuración para este carro
    $stmt = $conn->prepare("SELECT id FROM ventas_configuracion WHERE numero_carro = :numero_carro");
    $stmt->bindParam(':numero_carro', $numeroCarro);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Actualizar configuración existente
        $stmt = $conn->prepare("UPDATE ventas_configuracion SET 
            precio_promedio = :precio_promedio,
            costo_variable_porcentaje = :costo_variable_porcentaje,
            cantidad_vendida_dia = :cantidad_vendida_dia
            WHERE numero_carro = :numero_carro");
    } else {
        // Insertar nueva configuración
        $stmt = $conn->prepare("INSERT INTO ventas_configuracion (
            numero_carro, 
            precio_promedio, 
            costo_variable_porcentaje, 
            cantidad_vendida_dia
        ) VALUES (
            :numero_carro, 
            :precio_promedio, 
            :costo_variable_porcentaje, 
            :cantidad_vendida_dia
        )");
    }
    
    $stmt->bindParam(':numero_carro', $numeroCarro);
    $stmt->bindParam(':precio_promedio', $precioPromedio);
    $stmt->bindParam(':costo_variable_porcentaje', $costoVariablePorcentaje);
    $stmt->bindParam(':cantidad_vendida_dia', $cantidadVendidaDia);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Ventas actualizadas correctamente']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}