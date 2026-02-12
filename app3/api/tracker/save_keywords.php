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
$action = $input['action'] ?? '';
$data = $input['data'] ?? [];

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit();
}

try {
    mysqli_begin_transaction($conn);
    
    switch ($action) {
        case 'update_category':
            $id = $data['id'];
            $label = $data['label'];
            $weight = $data['weight'];
            $words = json_encode($data['words']);
            
            $update_query = "UPDATE job_keywords SET label = ?, weight = ?, words = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sdsi", $label, $weight, $words, $id);
            mysqli_stmt_execute($stmt);
            break;
            
        case 'add_category':
            $position = $data['position'];
            $category = $data['category'];
            $label = $data['label'];
            $weight = $data['weight'];
            $words = json_encode($data['words']);
            
            $insert_query = "INSERT INTO job_keywords (position, category, label, weight, words) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "sssds", $position, $category, $label, $weight, $words);
            mysqli_stmt_execute($stmt);
            break;
            
        case 'delete_category':
            $id = $data['id'];
            
            $delete_query = "DELETE FROM job_keywords WHERE id = ?";
            $stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
    mysqli_commit($conn);
    
    echo json_encode(['success' => true, 'message' => 'Keywords guardadas exitosamente']);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'Error al guardar keywords: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>