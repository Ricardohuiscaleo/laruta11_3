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
    // Verificar estructura de la tabla
    $query = "DESCRIBE usuarios";
    $result = mysqli_query($conn, $query);
    
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'telefono_exists' => in_array('telefono', array_column($columns, 'Field'))
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>