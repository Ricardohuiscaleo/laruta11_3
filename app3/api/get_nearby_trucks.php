<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Config file not found']));
}

// Crear conexión usando la configuración de app
$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

try {
    $lat = floatval($_POST['lat'] ?? 0);
    $lng = floatval($_POST['lng'] ?? 0);
    $radius = floatval($_POST['radius'] ?? 10); // Default 10km
    
    if ($lat == 0 || $lng == 0) {
        echo json_encode(['success' => false, 'error' => 'Coordenadas inválidas']);
        exit;
    }
    
    // Consulta para obtener food trucks con cálculo de distancia
    $sql = "SELECT 
                id,
                nombre,
                descripcion,
                direccion,
                latitud,
                longitud,
                horario_inicio,
                horario_fin,
                activo,
                tarifa_delivery,
                created_at,
                (6371 * acos(cos(radians(?)) * cos(radians(latitud)) * cos(radians(longitud) - radians(?)) + sin(radians(?)) * sin(radians(latitud)))) AS distance
            FROM food_trucks 
            HAVING distance <= ? 
            ORDER BY distance ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('dddd', $lat, $lng, $lat, $radius);
    $stmt->execute();
    $result = $stmt->get_result();
    $trucks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    // Formatear datos para el frontend
    $formattedTrucks = array_map(function($truck) {
        return [
            'id' => intval($truck['id']),
            'nombre' => $truck['nombre'],
            'descripcion' => $truck['descripcion'],
            'direccion' => $truck['direccion'],
            'latitud' => floatval($truck['latitud']),
            'longitud' => floatval($truck['longitud']),
            'horario_inicio' => $truck['horario_inicio'],
            'horario_fin' => $truck['horario_fin'],
            'activo' => intval($truck['activo']),
            'tarifa_delivery' => intval($truck['tarifa_delivery']),
            'distance' => floatval($truck['distance']),
            'created_at' => $truck['created_at']
        ];
    }, $trucks);
    
    echo json_encode([
        'success' => true,
        'trucks' => $formattedTrucks,
        'count' => count($formattedTrucks),
        'user_location' => [
            'lat' => $lat,
            'lng' => $lng
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>