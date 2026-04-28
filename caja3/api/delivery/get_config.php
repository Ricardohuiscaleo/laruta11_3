<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/delivery_config_helper.php';

// Defaults — se usan si no se puede conectar a BD
$defaults = [
    'tarifa_base'           => 3500,
    'card_surcharge'        => 500,
    'distance_threshold_km' => 6,
    'surcharge_per_bracket' => 1000,
    'bracket_size_km'       => 2,
    'rl6_discount_factor'   => 0.2857,
];

$loaded_from = 'defaults';

try {
    // Buscar config.php para conexión BD
    $config_paths = [
        __DIR__ . '/../../config.php',
        __DIR__ . '/../../../config.php',
        __DIR__ . '/../../../../config.php',
    ];
    $config = null;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            $config = require $path;
            break;
        }
    }

    if ($config) {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $result = get_delivery_config($pdo);

        // Verificar si se leyó de BD comparando con defaults
        // get_delivery_config ya retorna defaults si falla, así que chequeamos si la tabla existe
        $stmt = $pdo->query("SELECT COUNT(*) FROM delivery_config");
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            $loaded_from = 'database';
        }

        $defaults = $result;
    }
} catch (Exception $e) {
    // Fallo de conexión o tabla no existe → usar defaults
    $loaded_from = 'defaults';
}

echo json_encode([
    'success'               => true,
    'loaded_from'           => $loaded_from,
    'tarifa_base'           => (int) $defaults['tarifa_base'],
    'card_surcharge'        => (int) $defaults['card_surcharge'],
    'distance_threshold_km' => (int) $defaults['distance_threshold_km'],
    'surcharge_per_bracket' => (int) $defaults['surcharge_per_bracket'],
    'bracket_size_km'       => (int) $defaults['bracket_size_km'],
    'rl6_discount_factor'   => (float) $defaults['rl6_discount_factor'],
]);
