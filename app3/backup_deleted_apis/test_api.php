<?php
require_once '../config.php';

// Verificar si se solicita debug completo
if (isset($_GET['debug']) && $_GET['debug'] === 'full') {
    // Incluir el archivo de diagnóstico HTML
    include 'diagnostico.php';
    exit;
}

// Respuesta JSON normal
header('Content-Type: application/json');

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Verificar que la base de datos está respondiendo
try {
    $sql = "SELECT 1";
    $result = $conn->query($sql);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Conexión establecida correctamente',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al ejecutar consulta: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>