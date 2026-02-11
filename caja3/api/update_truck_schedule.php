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
    $dayOfWeek = $input['dayOfWeek'] ?? null;
    $horarioInicio = $input['horarioInicio'] ?? null;
    $horarioFin = $input['horarioFin'] ?? null;

    if ($truckId === null || $dayOfWeek === null) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE food_truck_schedules 
        SET horario_inicio = ?, horario_fin = ?, updated_at = NOW()
        WHERE food_truck_id = ? AND day_of_week = ?
    ");
    $stmt->execute([$horarioInicio, $horarioFin, $truckId, $dayOfWeek]);

    echo json_encode([
        'success' => true,
        'message' => 'Horario actualizado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
