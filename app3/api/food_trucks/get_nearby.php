<?php
header('Content-Type: application/json');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$lat = floatval($_POST['lat']);
$lng = floatval($_POST['lng']);
$radius = floatval($_POST['radius'] ?? 10); // 10km por defecto

if (!$lat || !$lng) {
    echo json_encode(['error' => 'Coordenadas inválidas']);
    exit();
}

// Verificar si la tabla existe
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'food_trucks'");
if (mysqli_num_rows($table_check) == 0) {
    // Crear tabla si no existe
    $create_table = "CREATE TABLE food_trucks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        descripcion TEXT,
        latitud DECIMAL(10, 8) NOT NULL,
        longitud DECIMAL(11, 8) NOT NULL,
        direccion TEXT NOT NULL,
        horario_inicio TIME,
        horario_fin TIME,
        dias_semana JSON,
        activo BOOLEAN DEFAULT TRUE,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $create_table);
    
    // Insertar datos de ejemplo
    $insert_data = "INSERT INTO food_trucks (nombre, descripcion, latitud, longitud, direccion, horario_inicio, horario_fin, dias_semana) VALUES 
        ('La Ruta 11 - Truck #1', 'Food truck principal - Especialidades de carne', -18.4647, -70.2997, 'Tucapel 2637, Arica, Arica y Parinacota', '11:00:00', '22:00:00', '[\"lunes\", \"martes\", \"miercoles\", \"jueves\", \"viernes\", \"sabado\"]'),
        ('La Ruta 11 - Truck #2', 'Food truck secundario - Completos y bebidas', -18.4647, -70.2997, 'Tucapel 2637, Arica, Arica y Parinacota', '11:00:00', '22:00:00', '[\"lunes\", \"martes\", \"miercoles\", \"jueves\", \"viernes\", \"sabado\", \"domingo\"]')";
    mysqli_query($conn, $insert_data);
}

// Obtener food trucks activos
$query = "SELECT * FROM food_trucks WHERE activo = TRUE";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => 'Error en consulta: ' . mysqli_error($conn)]);
    exit();
}

$nearby_trucks = [];

while ($truck = mysqli_fetch_assoc($result)) {
    // Calcular distancia
    $earth_radius = 6371; // km
    
    $lat_diff = deg2rad($lat - $truck['latitud']);
    $lng_diff = deg2rad($lng - $truck['longitud']);
    
    $a = sin($lat_diff/2) * sin($lat_diff/2) + 
         cos(deg2rad($truck['latitud'])) * cos(deg2rad($lat)) * 
         sin($lng_diff/2) * sin($lng_diff/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    if ($distance <= $radius) {
        $truck['distancia_km'] = round($distance, 2);
        $truck['dias_semana'] = json_decode($truck['dias_semana'], true);
        $nearby_trucks[] = $truck;
    }
}

// Ordenar por distancia
usort($nearby_trucks, function($a, $b) {
    return $a['distancia_km'] <=> $b['distancia_km'];
});

echo json_encode([
    'trucks' => $nearby_trucks,
    'count' => count($nearby_trucks)
]);

mysqli_close($conn);
?>