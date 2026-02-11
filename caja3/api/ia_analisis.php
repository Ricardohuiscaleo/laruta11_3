<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Función para obtener análisis
function obtenerAnalisis($conn, $tipo = null) {
    $sql = "SELECT * FROM ia_analisis";
    if ($tipo) {
        $sql .= " WHERE tipo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $tipo);
    } else {
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $analisis = [];
    
    while ($row = $result->fetch_assoc()) {
        $analisis[] = $row;
    }
    
    return $analisis;
}

// Función para actualizar análisis
function actualizarAnalisis($conn, $tipo, $contenido) {
    // Verificar si existe el análisis
    $sql = "SELECT id FROM ia_analisis WHERE tipo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $tipo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar análisis existente
        $sql = "UPDATE ia_analisis SET contenido = ? WHERE tipo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $contenido, $tipo);
    } else {
        // Insertar nuevo análisis
        $sql = "INSERT INTO ia_analisis (tipo, contenido) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $tipo, $contenido);
    }
    
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}

// Función para obtener prompt
function obtenerPrompt($conn, $nombre) {
    $sql = "SELECT * FROM ia_prompts WHERE nombre = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row;
    } else {
        return null;
    }
}

// Procesar la solicitud
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Obtener análisis
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
        
        if (isset($_GET['prompt']) && $_GET['prompt'] === 'true') {
            // Obtener prompt
            $nombre = isset($_GET['nombre']) ? $_GET['nombre'] : 'analisis_contable';
            $prompt = obtenerPrompt($conn, $nombre);
            echo json_encode(['success' => true, 'prompt' => $prompt]);
        } else {
            // Obtener análisis
            $analisis = obtenerAnalisis($conn, $tipo);
            echo json_encode(['success' => true, 'analisis' => $analisis]);
        }
        break;
        
    case 'POST':
        // Actualizar análisis
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['tipo']) || !isset($data['contenido'])) {
            echo json_encode(['success' => false, 'error' => 'Faltan parámetros requeridos']);
            break;
        }
        
        $tipo = $data['tipo'];
        $contenido = $data['contenido'];
        
        if (actualizarAnalisis($conn, $tipo, $contenido)) {
            echo json_encode(['success' => true, 'message' => 'Análisis actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar el análisis']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        break;
}

$conn->close();
?>