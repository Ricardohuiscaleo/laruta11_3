<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

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

try {
    // Obtener ID de columna "Nuevos"
    $stmt = mysqli_prepare($conn, "SELECT id FROM kanban_columns WHERE name = 'Nuevos' LIMIT 1");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $nuevos_column = mysqli_fetch_assoc($result);
    
    if (!$nuevos_column) {
        echo json_encode(['success' => false, 'error' => 'Columna Nuevos no encontrada']);
        exit();
    }
    
    $nuevos_column_id = $nuevos_column['id'];
    
    // Buscar aplicaciones completadas que no están en kanban_cards
    $query = "
        SELECT DISTINCT ja.user_id, ja.position
        FROM job_applications ja
        WHERE ja.status = 'completed'
        AND NOT EXISTS (
            SELECT 1 FROM kanban_cards kc 
            WHERE kc.user_id = ja.user_id AND kc.position = ja.position
        )
    ";
    
    $result = mysqli_query($conn, $query);
    $added = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Agregar a kanban_cards en columna "Nuevos"
        $stmt = mysqli_prepare($conn, "
            INSERT INTO kanban_cards (user_id, position, column_id, card_position, created_at) 
            VALUES (?, ?, ?, 0, NOW())
        ");
        mysqli_stmt_bind_param($stmt, "ssi", $row['user_id'], $row['position'], $nuevos_column_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $added++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Se agregaron $added aplicaciones al kanban",
        'added' => $added
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>