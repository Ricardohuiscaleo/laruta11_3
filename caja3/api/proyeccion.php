<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Verificar si se proporcionó un ID
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID de proyección no proporcionado']);
    exit;
}

$id = intval($_GET['id']);

try {
    // Conectar a la base de datos
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener datos de la proyección
    $stmt = $conn->prepare("SELECT * FROM proyecciones WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Proyección no encontrada']);
        exit;
    }
    
    $proyeccion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles de los carros
    $stmtDetalles = $conn->prepare("SELECT * FROM proyecciones_detalles WHERE proyeccion_id = :id");
    $stmtDetalles->bindParam(':id', $id);
    $stmtDetalles->execute();
    
    $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
    
    // Añadir detalles a la proyección
    $proyeccion['detalles'] = $detalles;
    
    // Devolver respuesta
    echo json_encode(['success' => true, 'data' => $proyeccion]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}