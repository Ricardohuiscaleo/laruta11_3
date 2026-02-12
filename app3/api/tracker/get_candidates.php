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
$config = require_once __DIR__ . '/../../config.php';

// Conectar a BD desde config central
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
    // Construir query con filtros
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($_GET['position'])) {
        $where_conditions[] = "position = ?";
        $params[] = $_GET['position'];
        $types .= 's';
    }
    
    if (!empty($_GET['status'])) {
        $where_conditions[] = "status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    if (!empty($_GET['min_score']) && is_numeric($_GET['min_score'])) {
        $where_conditions[] = "score >= ?";
        $params[] = floatval($_GET['min_score']);
        $types .= 'd';
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $query = "
        SELECT 
            latest.user_id,
            latest.nombre, 
            latest.telefono, 
            latest.instagram,
            latest.nacionalidad,
            latest.position, 
            latest.best_score,
            latest.status,
            latest.total_attempts,
            latest.last_attempt,
            latest.completed_at,
            latest.detected_skills,
            u.foto_perfil,
            u.kanban_status
        FROM (
            SELECT 
                ja.user_id,
                ja.nombre, 
                ja.telefono, 
                ja.instagram,
                ja.nacionalidad,
                ja.position, 
                MAX(ja.score) as best_score,
                COUNT(*) as total_attempts,
                MAX(ja.created_at) as last_attempt,
                MAX(ja.completed_at) as completed_at,
                MAX(ja.detected_skills) as detected_skills,
                SUBSTRING_INDEX(GROUP_CONCAT(ja.status ORDER BY ja.created_at DESC), ',', 1) as status
            FROM job_applications ja
            $where_clause
            GROUP BY ja.user_id, ja.position
        ) latest
        LEFT JOIN usuarios u ON latest.user_id = u.id
        ORDER BY latest.last_attempt DESC
        LIMIT 100
    ";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
    }
    
    $candidates = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $candidates[] = $row;
    }
    
    // Debug: verificar que kanban_status esté presente
    foreach ($candidates as &$candidate) {
        if (!isset($candidate['kanban_status'])) {
            $candidate['kanban_status'] = 'nuevo';
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $candidates
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>