<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

header('Content-Type: application/json');

try {
    // Verificar sesión
    $session_info = [
        'user_exists' => isset($_SESSION['user']),
        'user_id' => $_SESSION['user']['id'] ?? null,
        'user_name' => $_SESSION['user']['nombre'] ?? null
    ];
    
    // Verificar si tabla user_metrics existe
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'user_metrics'");
    $table_exists = mysqli_num_rows($table_check) > 0;
    
    // Verificar columnas en usuarios
    $user_columns = mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'total_sessions'");
    $user_column_exists = mysqli_num_rows($user_columns) > 0;
    
    $result = [
        'session' => $session_info,
        'table_user_metrics_exists' => $table_exists,
        'user_total_sessions_column_exists' => $user_column_exists,
        'database' => mysqli_get_server_info($conn)
    ];
    
    if ($table_exists && isset($_SESSION['user']['id'])) {
        $user_id = $_SESSION['user']['id'];
        $metrics_query = "SELECT COUNT(*) as total FROM user_metrics WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $metrics_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $metrics_result = mysqli_stmt_get_result($stmt);
        $result['user_metrics_count'] = mysqli_fetch_assoc($metrics_result)['total'];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

mysqli_close($conn);
?>