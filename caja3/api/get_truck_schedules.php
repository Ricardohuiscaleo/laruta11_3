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

// Obtener horarios por día
$stmt = $pdo->prepare("
SELECT
day_of_week,
horario_inicio,
horario_fin,
activo
FROM food_truck_schedules
WHERE food_truck_id = ?
ORDER BY day_of_week
");
$stmt->execute([$truckId]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener día actual en Chile (0=Domingo, 6=Sábado)
date_default_timezone_set('America/Santiago');
$currentDayOfWeek = (int)date('w'); // 0=Domingo, 6=Sábado

send_json([
'success' => true,
'schedules' => $schedules,
'currentDayOfWeek' => $currentDayOfWeek
]);

} catch (Exception $e) {
handle_api_exception($e);
}