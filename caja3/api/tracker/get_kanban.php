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

try {
    // Obtener columnas del Kanban
    $columns_query = "SELECT * FROM kanban_columns ORDER BY position";
    $columns_result = mysqli_query($conn, $columns_query);
    $columns = [];
    
    while ($row = mysqli_fetch_assoc($columns_result)) {
        $columns[] = $row;
    }
    
    // Obtener tarjetas con datos del candidato
    $cards_query = "
        SELECT 
            kc.*,
            ja.nombre,
            ja.telefono,
            ja.instagram,
            ja.nacionalidad,
            ja.genero,
            MAX(ja.score) as best_score,
            COUNT(ja.id) as total_attempts,
            MAX(ja.created_at) as last_attempt,
            ja.detected_skills,
            u.foto_perfil,
            u.email,
            COALESCE(u.kanban_status, 'nuevo') as kanban_status
        FROM kanban_cards kc
        JOIN job_applications ja ON kc.user_id = ja.user_id AND kc.position = ja.position
        LEFT JOIN usuarios u ON kc.user_id = u.id
        GROUP BY kc.id, u.kanban_status
        ORDER BY kc.column_id, kc.card_position
    ";
    
    $cards_result = mysqli_query($conn, $cards_query);
    $cards = [];
    
    while ($row = mysqli_fetch_assoc($cards_result)) {
        // Decodificar skills si existen
        if ($row['detected_skills']) {
            $row['detected_skills'] = json_decode($row['detected_skills'], true);
        }
        $cards[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'columns' => $columns,
            'cards' => $cards
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>