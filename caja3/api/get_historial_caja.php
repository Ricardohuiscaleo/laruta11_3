<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
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

$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexion']);
    exit;
}

mysqli_set_charset($conn, "utf8");

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

$sql = "SELECT * FROM caja_movimientos ORDER BY id DESC LIMIT " . intval($limit);
$result = mysqli_query($conn, $sql);

$movimientos = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $movimientos[] = $row;
    }
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'movimientos' => $movimientos
]);
?>
