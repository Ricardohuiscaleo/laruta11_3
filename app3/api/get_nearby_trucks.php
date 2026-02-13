<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Obtener todos los food trucks activos
    $stmt = $pdo->prepare("SELECT * FROM food_trucks WHERE activo = 1 ORDER BY nombre");
    $stmt->execute();
    $trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si se envían coordenadas, calcular distancia
    if (isset($_POST['lat']) && isset($_POST['lng'])) {
        $userLat = floatval($_POST['lat']);
        $userLng = floatval($_POST['lng']);
        
        foreach ($trucks as &$truck) {
            // Fórmula Haversine para calcular distancia
            $earthRadius = 6371; // km
            
            $latDiff = deg2rad($userLat - $truck['latitud']);
            $lngDiff = deg2rad($userLng - $truck['longitud']);
            
            $a = sin($latDiff/2) * sin($latDiff/2) +
                 cos(deg2rad($truck['latitud'])) * cos(deg2rad($userLat)) *
                 sin($lngDiff/2) * sin($lngDiff/2);
            
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earthRadius * $c;
            
            $truck['distance'] = round($distance, 2);
        }
        
        // Ordenar por distancia
        usort($trucks, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
    }

    echo json_encode([
        'success' => true,
        'trucks' => $trucks
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
