<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Configurar conexión a BD MySQL
$conn = new mysqli(
    $config['mysql_host'],
    $config['mysql_user'],
    $config['mysql_pass'],
    $config['mysql_db']
);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión MySQL: ' . $conn->connect_error]));
}

$conn->set_charset('utf8');

try {
    // Verificar keywords disponibles
    $query = "SELECT * FROM job_keywords ORDER BY category";
    $result = $conn->query($query);
    $keywords = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['words'] = json_decode($row['words'], true);
        $keywords[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'total_keywords' => count($keywords),
        'keywords' => $keywords
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>