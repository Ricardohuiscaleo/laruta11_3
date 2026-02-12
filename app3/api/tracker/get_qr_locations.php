<?php
session_start();

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

// Conectar usando config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

try {
    // Obtener ubicaciones con coordenadas válidas
    $query = "SELECT latitude, longitude, city, region, country, COUNT(*) as views 
              FROM qr_views 
              WHERE latitude IS NOT NULL AND longitude IS NOT NULL 
              GROUP BY latitude, longitude, city, region, country";
    
    $result = mysqli_query($conn, $query);
    $locations = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $locations[] = [
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude'],
            'views' => (int)$row['views'],
            'city' => $row['city'] ?: 'Ciudad desconocida',
            'region' => $row['region'] ?: 'Región desconocida',
            'country' => $row['country'] ?: 'País desconocido'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'locations' => $locations
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>