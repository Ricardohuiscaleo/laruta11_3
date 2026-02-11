<?php
session_start();
header('Content-Type: application/json');

// Cache busting headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Verificar autenticación
if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a BD
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

// Obtener datos POST
$input = json_decode(file_get_contents('php://input'), true);
$card_id = $input['card_id'] ?? '';
$to_column_id = $input['to_column_id'] ?? '';
$new_position = $input['new_position'] ?? 0;
$notes = $input['notes'] ?? '';

if (empty($card_id) || empty($to_column_id)) {
    echo json_encode(['success' => false, 'error' => 'Datos requeridos faltantes']);
    exit();
}

try {
    mysqli_begin_transaction($conn);
    
    // Obtener columna actual y user_id
    $current_query = "SELECT column_id, user_id FROM kanban_cards WHERE id = ?";
    $stmt = mysqli_prepare($conn, $current_query);
    mysqli_stmt_bind_param($stmt, "i", $card_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current = mysqli_fetch_assoc($result);
    
    if (!$current) {
        throw new Exception('Tarjeta no encontrada');
    }
    
    // Mapear columnas a estados
    $column_to_status = [
        1 => 'nuevo',
        2 => 'revisando',
        3 => 'entrevista', 
        4 => 'contratado',
        5 => 'rechazado'
    ];
    
    // Actualizar posición de la tarjeta
    $update_query = "UPDATE kanban_cards SET column_id = ?, card_position = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "iii", $to_column_id, $new_position, $card_id);
    mysqli_stmt_execute($stmt);
    
    // Actualizar kanban_status en usuarios si hay mapeo
    if (isset($column_to_status[$to_column_id])) {
        $new_status = $column_to_status[$to_column_id];
        $update_status_query = "UPDATE usuarios SET kanban_status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_status_query);
        mysqli_stmt_bind_param($stmt, "si", $new_status, $current['user_id']);
        mysqli_stmt_execute($stmt);
    }
    
    // Registrar en historial
    $history_query = "INSERT INTO kanban_history (card_id, from_column_id, to_column_id, moved_by, notes) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $history_query);
    $moved_by = $_SESSION['tracker_user']['nombre'];
    mysqli_stmt_bind_param($stmt, "iiiss", $card_id, $current['column_id'], $to_column_id, $moved_by, $notes);
    mysqli_stmt_execute($stmt);
    
    mysqli_commit($conn);
    
    echo json_encode(['success' => true, 'message' => 'Tarjeta movida exitosamente']);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'Error al mover tarjeta: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>