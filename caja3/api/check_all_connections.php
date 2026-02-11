<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

header('Content-Type: application/json');

try {
    // Verificar conexión actual
    $current_db = mysqli_query($conn, "SELECT DATABASE() as db_name");
    $db_name = mysqli_fetch_assoc($current_db)['db_name'];
    
    // Verificar tablas
    $tables_query = "SHOW TABLES";
    $tables_result = mysqli_query($conn, $tables_query);
    $tables = [];
    while ($row = mysqli_fetch_array($tables_result)) {
        $tables[] = $row[0];
    }
    
    echo json_encode([
        'success' => true,
        'current_database' => $db_name,
        'expected_database' => 'u958525313_usuariosruta11',
        'tables' => $tables,
        'user_metrics_exists' => in_array('user_metrics', $tables),
        'usuarios_exists' => in_array('usuarios', $tables)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>