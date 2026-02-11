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

// Por ahora, productos basados en región
// Más adelante se puede expandir con productos específicos por zona

$region_products = [];

// Detectar región usando coordenadas
if ($lat >= -18.5 && $lat <= -18.4 && $lng >= -70.4 && $lng <= -70.2) {
    // Zona Arica - productos especiales
    $region_products = [
        'featured' => [
            'title' => '🌮 Especiales de Arica',
            'products' => [
                ['id' => 101, 'name' => 'Tomahawk Papa', 'discount' => 10],
                ['id' => 105, 'name' => 'Completo Talquino', 'discount' => 15]
            ]
        ],
        'delivery_info' => [
            'available' => true,
            'estimated_time' => '25-35 min',
            'cost' => 2000
        ]
    ];
} else {
    // Zona general
    $region_products = [
        'featured' => [
            'title' => '🍔 Productos Populares',
            'products' => [
                ['id' => 1, 'name' => 'Churrasco Vacuno', 'discount' => 5],
                ['id' => 5, 'name' => 'Completo Tradicional', 'discount' => 0]
            ]
        ],
        'delivery_info' => [
            'available' => false,
            'message' => 'Delivery no disponible en tu zona'
        ]
    ];
}

echo json_encode($region_products);
?>