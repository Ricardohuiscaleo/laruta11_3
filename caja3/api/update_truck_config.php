<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

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

    $input = json_decode(file_get_contents('php://input'), true);
    $truckId = $input['truckId'] ?? null;
    
    if (!$truckId) {
        echo json_encode(['success' => false, 'error' => 'ID de truck requerido']);
        exit;
    }

    $updates = [];
    $params = [];

    if (isset($input['direccion'])) {
        $updates[] = "direccion = ?";
        $params[] = $input['direccion'];
    }
    if (isset($input['latitud'])) {
        $updates[] = "latitud = ?";
        $params[] = $input['latitud'];
    }
    if (isset($input['longitud'])) {
        $updates[] = "longitud = ?";
        $params[] = $input['longitud'];
    }
    if (isset($input['horario_inicio'])) {
        $updates[] = "horario_inicio = ?";
        $params[] = $input['horario_inicio'];
    }
    if (isset($input['horario_fin'])) {
        $updates[] = "horario_fin = ?";
        $params[] = $input['horario_fin'];
    }
    if (isset($input['tarifa_delivery'])) {
        $updates[] = "tarifa_delivery = ?";
        $params[] = $input['tarifa_delivery'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'No hay datos para actualizar']);
        exit;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $truckId;

    $sql = "UPDATE food_trucks SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Configuración actualizada correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
