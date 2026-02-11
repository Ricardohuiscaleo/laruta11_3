<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Verificar que se recibieron los datos necesarios
if (!isset($_POST['valor_activos']) || !isset($_POST['vida_util'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Datos incompletos'
    ]));
}

$valor_activos = floatval($_POST['valor_activos']);
$vida_util = intval($_POST['vida_util']);

// Validar datos
if ($valor_activos < 0 || $vida_util <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Valores inválidos'
    ]));
}

// Insertar nuevos datos
$sql = "INSERT INTO activos_v2 (valor_activos, vida_util) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("di", $valor_activos, $vida_util);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Activos actualizados correctamente'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar activos: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>