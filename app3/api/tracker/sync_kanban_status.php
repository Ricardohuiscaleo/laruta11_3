<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$config = require_once __DIR__ . '/../../config.php';

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

try {
    mysqli_begin_transaction($conn);
    
    // Mapear columnas a estados
    $column_to_status = [
        1 => 'nuevo',
        2 => 'revisando', 
        3 => 'entrevista',
        4 => 'contratado',
        5 => 'rechazado'
    ];
    
    // Sincronizar desde kanban_cards hacia usuarios
    $sync_query = "
        UPDATE usuarios u 
        JOIN kanban_cards kc ON u.id = kc.user_id 
        SET u.kanban_status = CASE 
            WHEN kc.column_id = 1 THEN 'nuevo'
            WHEN kc.column_id = 2 THEN 'revisando'
            WHEN kc.column_id = 3 THEN 'entrevista'
            WHEN kc.column_id = 4 THEN 'contratado'
            WHEN kc.column_id = 5 THEN 'rechazado'
            ELSE 'nuevo'
        END
        WHERE u.kanban_status IS NULL OR u.kanban_status = ''
    ";
    
    mysqli_query($conn, $sync_query);
    $synced = mysqli_affected_rows($conn);
    
    // También sincronizar en la dirección opuesta para usuarios sin tarjeta
    $status_to_column = [
        'nuevo' => 1,
        'revisando' => 2,
        'entrevista' => 3, 
        'contratado' => 4,
        'rechazado' => 5
    ];
    
    // Obtener usuarios con kanban_status pero sin tarjeta
    $users_query = "
        SELECT u.id, u.kanban_status 
        FROM usuarios u 
        LEFT JOIN kanban_cards kc ON u.id = kc.user_id 
        WHERE kc.user_id IS NULL 
        AND u.kanban_status IS NOT NULL 
        AND u.kanban_status != ''
    ";
    
    $result = mysqli_query($conn, $users_query);
    $created_cards = 0;
    
    while ($user = mysqli_fetch_assoc($result)) {
        $column_id = $status_to_column[$user['kanban_status']] ?? 1;
        
        // Crear tarjeta kanban para este usuario
        $insert_card = "INSERT INTO kanban_cards (user_id, column_id, position, card_position) VALUES (?, ?, 'cajero', 0)";
        $stmt = mysqli_prepare($conn, $insert_card);
        mysqli_stmt_bind_param($stmt, "ii", $user['id'], $column_id);
        mysqli_stmt_execute($stmt);
        $created_cards++;
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => "Sincronización completada. $synced usuarios actualizados, $created_cards tarjetas creadas."
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>