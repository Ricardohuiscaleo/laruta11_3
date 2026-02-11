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
    die('Error de conexión a BD');
}

mysqli_set_charset($conn, 'utf8');

// Dirección exacta
$address = "Tucapel 2637, Arica, Chile";

// Usar Google Geocoding API para obtener coordenadas exactas
$url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $config['ruta11_google_maps_api_key'];

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['status'] === 'OK' && !empty($data['results'])) {
    $result = $data['results'][0];
    $lat = $result['geometry']['location']['lat'];
    $lng = $result['geometry']['location']['lng'];
    $formatted_address = $result['formatted_address'];
    
    echo "<h2>Coordenadas exactas para Tucapel 2637, Arica:</h2>";
    echo "<p><strong>Latitud:</strong> $lat</p>";
    echo "<p><strong>Longitud:</strong> $lng</p>";
    echo "<p><strong>Dirección formateada:</strong> $formatted_address</p>";
    
    // Actualizar base de datos
    $update_query = "UPDATE food_trucks SET 
        latitud = $lat, 
        longitud = $lng, 
        direccion = '$formatted_address' 
        WHERE nombre LIKE '%Truck%'";
    
    if (mysqli_query($conn, $update_query)) {
        echo "<br><p style='color: green;'>✅ Base de datos actualizada correctamente</p>";
    } else {
        echo "<br><p style='color: red;'>❌ Error actualizando base de datos: " . mysqli_error($conn) . "</p>";
    }
    
} else {
    echo "Error obteniendo coordenadas: " . $data['status'];
}

mysqli_close($conn);
?>