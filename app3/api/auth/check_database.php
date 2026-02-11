<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

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

header('Content-Type: application/json');

try {
    // Verificar qué base de datos estamos usando
    $db_query = "SELECT DATABASE() as current_db";
    $db_result = mysqli_query($conn, $db_query);
    $current_db = mysqli_fetch_assoc($db_result)['current_db'];
    
    // Verificar estructura de la tabla usuarios
    $query = "DESCRIBE usuarios";
    $result = mysqli_query($conn, $query);
    
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    
    echo json_encode([
        'success' => true,
        'current_database' => $current_db,
        'columns' => $columns,
        'has_telefono' => in_array('telefono', $columns),
        'has_instagram' => in_array('instagram', $columns),
        'has_google_id' => in_array('google_id', $columns)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>