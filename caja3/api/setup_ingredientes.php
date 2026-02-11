<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once '../config.php';

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

try {
    // Verificar si ya existen ingredientes
    $result = $conn->query("SELECT COUNT(*) as count FROM ingredientes");
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => true, 'message' => 'Los ingredientes ya están configurados (' . $row['count'] . ' ingredientes)']);
        exit;
    }
    
    // Leer y ejecutar el archivo SQL
    $sqlFile = __DIR__ . '/seed_ingredientes.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('Archivo seed_ingredientes.sql no encontrado');
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Dividir en consultas individuales
    $queries = explode(';', $sql);
    $executed = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query) || strpos($query, '--') === 0) continue;
        
        if ($conn->query($query)) {
            $executed++;
        } else {
            throw new Exception('Error ejecutando consulta: ' . $conn->error);
        }
    }
    
    // Verificar ingredientes insertados
    $result = $conn->query("SELECT COUNT(*) as count FROM ingredientes");
    $row = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ingredientes configurados correctamente',
        'ingredientes_count' => $row['count'],
        'queries_executed' => $executed
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>