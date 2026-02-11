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
if (!isset($data['numero_carros']) || 
    !isset($data['sueldo_base']) || 
    !isset($data['cargas_sociales_porcentaje']) || 
    !isset($data['permisos_por_carro']) || 
    !isset($data['servicios_por_carro']) || 
    !isset($data['otros_fijos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$numeroCarros = intval($data['numero_carros']);
$sueldoBase = floatval($data['sueldo_base']);
$cargasSocialesPorcentaje = floatval($data['cargas_sociales_porcentaje']);
$permisosPorCarro = floatval($data['permisos_por_carro']);
$serviciosPorCarro = floatval($data['servicios_por_carro']);
$otrosFijos = floatval($data['otros_fijos']);

// Validar valores
if ($numeroCarros <= 0 || $sueldoBase < 0 || $cargasSocialesPorcentaje < 0 || 
    $permisosPorCarro < 0 || $serviciosPorCarro < 0 || $otrosFijos < 0) {
    echo json_encode(['success' => false, 'message' => 'Valores inválidos']);
    exit;
}

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si existe una configuración global
    $stmt = $conn->prepare("SELECT id FROM configuracion WHERE id = 1");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Actualizar configuración existente
        $stmt = $conn->prepare("UPDATE configuracion SET 
            numero_carros = :numero_carros,
            sueldo_base = :sueldo_base,
            cargas_sociales_porcentaje = :cargas_sociales_porcentaje,
            permisos_por_carro = :permisos_por_carro,
            servicios_por_carro = :servicios_por_carro,
            otros_fijos = :otros_fijos
            WHERE id = 1");
    } else {
        // Insertar nueva configuración
        $stmt = $conn->prepare("INSERT INTO configuracion (
            id, 
            numero_carros, 
            sueldo_base, 
            cargas_sociales_porcentaje, 
            permisos_por_carro, 
            servicios_por_carro, 
            otros_fijos
        ) VALUES (
            1, 
            :numero_carros, 
            :sueldo_base, 
            :cargas_sociales_porcentaje, 
            :permisos_por_carro, 
            :servicios_por_carro, 
            :otros_fijos
        )");
    }
    
    $stmt->bindParam(':numero_carros', $numeroCarros);
    $stmt->bindParam(':sueldo_base', $sueldoBase);
    $stmt->bindParam(':cargas_sociales_porcentaje', $cargasSocialesPorcentaje);
    $stmt->bindParam(':permisos_por_carro', $permisosPorCarro);
    $stmt->bindParam(':servicios_por_carro', $serviciosPorCarro);
    $stmt->bindParam(':otros_fijos', $otrosFijos);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Costos fijos actualizados correctamente']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}