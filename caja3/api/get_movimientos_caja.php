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

$isMonthView = isset($_GET['month']) && (isset($_GET['year']) || $_GET['month'] === 'current');

if ($isMonthView) {
    if (isset($_GET['year']) && is_numeric($_GET['month'])) {
        // Mes y año específicos
        $year = (int)$_GET['year'];
        $month = (int)$_GET['month'];
        $firstDay = new DateTime("$year-$month-01", new DateTimeZone('America/Santiago'));
        $lastDay = new DateTime($firstDay->format('Y-m-t'), new DateTimeZone('America/Santiago'));
        
        $start_date_chile = $firstDay->format('Y-m-d') . ' 16:00:00';
        $end_date_chile = $lastDay->format('Y-m-d') . ' 23:59:59';
    } else {
        // Mes actual
        $now = new DateTime('now', new DateTimeZone('America/Santiago'));
        $firstDay = new DateTime($now->format('Y-m-01'), new DateTimeZone('America/Santiago'));
        
        $start_date_chile = $firstDay->format('Y-m-d') . ' 16:00:00';
        $end_date_chile = $now->format('Y-m-d H:i:s');
    }
    
    $start_date = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
} else {
    $daysAgo = isset($_GET['days_ago']) ? (int)$_GET['days_ago'] : 0;

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHourChile = (int)$now->format('G');

    $shiftStartDate = $now->format('Y-m-d');
    if ($currentHourChile >= 0 && $currentHourChile < 4) {
        $shiftStartDate = date('Y-m-d', strtotime($shiftStartDate . ' -1 day'));
    }

    if ($daysAgo > 0) {
        $shiftStartDate = date('Y-m-d', strtotime($shiftStartDate . ' -' . $daysAgo . ' days'));
    }

    $start_date_chile = $shiftStartDate . ' 16:00:00';
    $end_date_chile = date('Y-m-d', strtotime($shiftStartDate . ' +1 day')) . ' 04:00:00';

    $start_date = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
}

// Obtener movimientos del turno
$sql = "SELECT * FROM caja_movimientos 
        WHERE fecha_movimiento >= ? 
        AND fecha_movimiento < ?
        ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$movimientos = [];
$total_ingresos = 0;
$total_retiros = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $movimientos[] = $row;
        if ($row['tipo'] === 'ingreso') {
            $total_ingresos += floatval($row['monto']);
        } else {
            $total_retiros += floatval($row['monto']);
        }
    }
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'movimientos' => $movimientos,
    'total_ingresos' => $total_ingresos,
    'total_retiros' => $total_retiros
]);
?>
