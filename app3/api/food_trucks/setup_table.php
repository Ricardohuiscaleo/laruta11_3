<?php
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die('Configuración no encontrada');
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die('Error de conexión: ' . $conn->connect_error);
}

// Crear tabla food_trucks si no existe
$sql = "CREATE TABLE IF NOT EXISTS food_trucks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    direccion VARCHAR(500) NOT NULL,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    horario_inicio TIME DEFAULT '10:00:00',
    horario_fin TIME DEFAULT '22:00:00',
    activo BOOLEAN DEFAULT TRUE,
    tarifa_delivery INT DEFAULT 2000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Tabla food_trucks creada/verificada exitosamente\n";
    
    // Insertar datos de ejemplo si la tabla está vacía
    $result = $conn->query("SELECT COUNT(*) as count FROM food_trucks");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $trucks = [
            ['La Ruta 11 - Plaza Maipú', 'Food truck principal en Plaza Maipú', 'Plaza de Maipú, Maipú, Chile', -33.5110, -70.7580, '10:00:00', '22:00:00', 1, 2000],
            ['La Ruta 11 - Parque O\'Higgins', 'Sucursal en Parque O\'Higgins', 'Parque O\'Higgins, Santiago, Chile', -33.4600, -70.6500, '11:00:00', '21:00:00', 1, 2500],
            ['La Ruta 11 - Las Condes', 'Food truck en Las Condes', 'Av. Apoquindo, Las Condes, Chile', -33.4100, -70.5800, '12:00:00', '23:00:00', 0, 3000]
        ];
        
        $stmt = $conn->prepare("INSERT INTO food_trucks (nombre, descripcion, direccion, latitud, longitud, horario_inicio, horario_fin, activo, tarifa_delivery) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($trucks as $truck) {
            $stmt->bind_param('sssddssii', ...$truck);
            $stmt->execute();
        }
        
        echo "Datos de ejemplo insertados\n";
    }
} else {
    echo "Error creando tabla: " . $conn->error;
}

$conn->close();
?>