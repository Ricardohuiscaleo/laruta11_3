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
if (!isset($data['valor_activos']) || !isset($data['vida_util_anios'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$valorActivos = floatval($data['valor_activos']);
$vidaUtilAnios = intval($data['vida_util_anios']);

// Validar valores
if ($valorActivos < 0 || $vidaUtilAnios <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valores inválidos']);
    exit;
}

try {
    // Verificar si existe una configuración global
    $stmt = $conn->prepare("SELECT id FROM configuracion WHERE id = 1");
    $stmt->execute();
    
    if ($stmt->num_rows > 0) {
        // Actualizar configuración existente
        $stmt = $conn->prepare("UPDATE configuracion SET 
            valor_activos = ?,
            vida_util_anios = ?
            WHERE id = 1");
        $stmt->bind_param("di", $valorActivos, $vidaUtilAnios);
    } else {
        // Insertar nueva configuración
        $stmt = $conn->prepare("INSERT INTO configuracion (
            id, 
            valor_activos, 
            vida_util_anios
        ) VALUES (
            1, 
            ?,
            ?
        )");
        $stmt->bind_param("di", $valorActivos, $vidaUtilAnios);
    }
    
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Activos actualizados correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}