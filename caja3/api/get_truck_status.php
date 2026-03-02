<?php
require_once __DIR__ . '/api_helper.php';
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    send_json(['success' => false, 'error' => 'Config no encontrado']);
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

    $truckId = $_GET['truckId'] ?? 4;

    $stmt = $pdo->prepare("
SELECT
id,
nombre,
descripcion,
direccion,
horario_inicio,
horario_fin,
activo,
tarifa_delivery
FROM food_trucks
WHERE id = ?
");
    $stmt->execute([$truckId]);
    $truck = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($truck) {
        send_json([
            'success' => true,
            'truck' => $truck
        ]);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Food truck no encontrado']);
    }

}
catch (Exception $e) {
    handle_api_exception($e);
}