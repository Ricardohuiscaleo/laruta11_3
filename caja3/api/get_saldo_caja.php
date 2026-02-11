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

// Obtener saldo actual (último registro)
$sql = "SELECT saldo_nuevo FROM caja_movimientos ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);
$saldo_actual = 0;

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $saldo_actual = floatval($row['saldo_nuevo']);
}

// Obtener total de efectivo del día actual (solo de tuu_orders)
$sql_cash = "SELECT COALESCE(SUM(installment_amount), 0) as total_cash 
             FROM tuu_orders 
             WHERE payment_method = 'cash' 
             AND payment_status = 'paid'
             AND DATE(created_at) = CURDATE()";
$result_cash = mysqli_query($conn, $sql_cash);
$total_cash = 0;

if ($result_cash && mysqli_num_rows($result_cash) > 0) {
    $row = mysqli_fetch_assoc($result_cash);
    $total_cash = floatval($row['total_cash']);
}

// Obtener total de ingresos automáticos del día
$sql_auto = "SELECT COALESCE(SUM(monto), 0) as ingresos_auto
             FROM caja_movimientos
             WHERE tipo = 'ingreso'
             AND usuario = 'Sistema'
             AND DATE(fecha_movimiento) = CURDATE()";
$result_auto = mysqli_query($conn, $sql_auto);
$ingresos_auto = 0;

if ($result_auto && mysqli_num_rows($result_auto) > 0) {
    $row = mysqli_fetch_assoc($result_auto);
    $ingresos_auto = floatval($row['ingresos_auto']);
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'saldo_actual' => $saldo_actual,
    'efectivo_dia' => $total_cash,
    'ingresos_automaticos_dia' => $ingresos_auto
]);
?>
