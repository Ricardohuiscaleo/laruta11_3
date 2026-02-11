<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

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
    echo json_encode(['error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

$lat = floatval($_POST['lat']);
$lng = floatval($_POST['lng']);

if (!$lat || !$lng) {
    echo json_encode(['error' => 'Coordenadas inválidas']);
    exit();
}

// Verificar si la tabla existe
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'delivery_zones'");
if (mysqli_num_rows($table_check) == 0) {
    // Crear tabla si no existe
    $create_table = "CREATE TABLE delivery_zones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        centro_lat DECIMAL(10, 8) NOT NULL,
        centro_lng DECIMAL(11, 8) NOT NULL,
        radio_km DECIMAL(5, 2) NOT NULL,
        tiempo_estimado_min INT NOT NULL,
        costo_delivery DECIMAL(8, 2) DEFAULT 0,
        activa BOOLEAN DEFAULT TRUE,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $create_table);
    
    // Insertar zona de Arica
    $insert_zone = "INSERT INTO delivery_zones (nombre, centro_lat, centro_lng, radio_km, tiempo_estimado_min, costo_delivery) 
                    VALUES ('Arica Centro', -18.4783, -70.3126, 5.0, 30, 2000)";
    mysqli_query($conn, $insert_zone);
}

// Obtener zonas activas
$query = "SELECT * FROM delivery_zones WHERE activa = TRUE";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => 'Error en consulta: ' . mysqli_error($conn)]);
    exit();
}

$available_zones = [];
$in_zone = false;

while ($zone = mysqli_fetch_assoc($result)) {
    // Calcular distancia usando fórmula Haversine
    $earth_radius = 6371; // km
    
    $lat_diff = deg2rad($lat - $zone['centro_lat']);
    $lng_diff = deg2rad($lng - $zone['centro_lng']);
    
    $a = sin($lat_diff/2) * sin($lat_diff/2) + 
         cos(deg2rad($zone['centro_lat'])) * cos(deg2rad($lat)) * 
         sin($lng_diff/2) * sin($lng_diff/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    if ($distance <= $zone['radio_km']) {
        $in_zone = true;
        $available_zones[] = [
            'id' => $zone['id'],
            'nombre' => $zone['nombre'],
            'tiempo_estimado' => $zone['tiempo_estimado_min'],
            'costo_delivery' => $zone['costo_delivery'],
            'distancia_km' => round($distance, 2)
        ];
    }
}

echo json_encode([
    'in_delivery_zone' => $in_zone,
    'zones' => $available_zones,
    'message' => $in_zone ? 'Delivery disponible' : 'Fuera de zona de delivery'
]);

mysqli_close($conn);
?>