<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir archivo de configuración
require_once __DIR__ . '/../config.php';

// Verificar que sea una solicitud POST o DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Método no permitido"
    ]);
    exit;
}

// Obtener ID de la proyección a eliminar
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Se requiere un ID de proyección"
    ]);
    exit;
}

$id = intval($data['id']);

// Iniciar transacción
mysqli_begin_transaction($conn);

try {
    // Eliminar detalles de la proyección primero (por la restricción de clave foránea)
    $sql_delete_detalles = "DELETE FROM detalles_proyeccion WHERE proyeccion_id = $id";
    if (!mysqli_query($conn, $sql_delete_detalles)) {
        throw new Exception("Error al eliminar detalles de la proyección: " . mysqli_error($conn));
    }
    
    // Eliminar la proyección
    $sql_delete = "DELETE FROM proyecciones_financieras WHERE id = $id";
    if (!mysqli_query($conn, $sql_delete)) {
        throw new Exception("Error al eliminar la proyección: " . mysqli_error($conn));
    }
    
    // Verificar si se eliminó algún registro
    if (mysqli_affected_rows($conn) == 0) {
        throw new Exception("No se encontró ninguna proyección con el ID proporcionado");
    }
    
    // Confirmar transacción
    mysqli_commit($conn);
    
    // Devolver respuesta exitosa
    echo json_encode([
        "success" => true,
        "message" => "Proyección eliminada correctamente"
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    mysqli_rollback($conn);
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>