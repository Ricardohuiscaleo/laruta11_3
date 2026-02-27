<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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

        $start_date_chile = $firstDay->format('Y-m-d') . ' 17:00:00';
        $end_date_chile = $lastDay->format('Y-m-d') . ' 23:59:59';
    }
    else {
        // Mes actual
        $now = new DateTime('now', new DateTimeZone('America/Santiago'));
        $firstDay = new DateTime($now->format('Y-m-01'), new DateTimeZone('America/Santiago'));

        $start_date_chile = $firstDay->format('Y-m-d') . ' 17:00:00';
        $end_date_chile = $now->format('Y-m-d H:i:s');
    }

    $start_date = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
}
else {
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

    $start_date_chile = $shiftStartDate . ' 17:00:00';
    $end_date_chile = date('Y-m-d', strtotime($shiftStartDate . ' +1 day')) . ' 04:00:00';

    $start_date = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
    $end_date = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
}

// Verificar qué columnas existen
$checkColumns = mysqli_query($conn, "SHOW COLUMNS FROM tuu_orders");
$columns = [];
while ($col = mysqli_fetch_assoc($checkColumns)) {
    $columns[] = $col['Field'];
}

$hasDeliveryFee = in_array('delivery_fee', $columns);
$hasTotalCost = in_array('total_cost', $columns);

$selectFields = "order_number, customer_name, product_name, installment_amount, payment_method, created_at, customer_notes, delivery_type";
if ($hasDeliveryFee)
    $selectFields .= ", delivery_fee";
if ($hasTotalCost)
    $selectFields .= ", total_cost";

$sql = "SELECT $selectFields 
        FROM tuu_orders 
        WHERE created_at >= ? 
        AND created_at < ?
        AND payment_status = 'paid'
        AND order_number NOT LIKE 'RL6-%'
        ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ventas = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Asegurar que siempre existan estos campos
        if (!isset($row['delivery_fee']))
            $row['delivery_fee'] = 0;
        if (!isset($row['total_cost']))
            $row['total_cost'] = 0;
        $ventas[] = $row;
    }
}

// Obtener costo desde tuu_order_items.item_cost
foreach ($ventas as &$venta) {
    $orderIdSql = "SELECT id FROM tuu_orders WHERE order_number = ? LIMIT 1";
    $orderIdStmt = mysqli_prepare($conn, $orderIdSql);
    if ($orderIdStmt) {
        mysqli_stmt_bind_param($orderIdStmt, "s", $venta['order_number']);
        mysqli_stmt_execute($orderIdStmt);
        $orderIdResult = mysqli_stmt_get_result($orderIdStmt);
        $orderRow = mysqli_fetch_assoc($orderIdResult);

        if ($orderRow) {
            $orderId = $orderRow['id'];

            // Sumar item_cost de todos los items de la orden
            $costSql = "SELECT COALESCE(SUM(item_cost * quantity), 0) as total_cost FROM tuu_order_items WHERE order_id = ?";
            $costStmt = mysqli_prepare($conn, $costSql);
            if ($costStmt) {
                mysqli_stmt_bind_param($costStmt, "i", $orderId);
                mysqli_stmt_execute($costStmt);
                $costResult = mysqli_stmt_get_result($costStmt);
                $costRow = mysqli_fetch_assoc($costResult);
                $venta['total_cost'] = round(floatval($costRow['total_cost']), 2);
            }
        }
    }
}
unset($venta);

// Calcular estadísticas
$pickupCount = 0;
$deliveryCount = 0;
$totalCost = 0;
foreach ($ventas as $v) {
    if (isset($v['delivery_type']) && $v['delivery_type'] === 'delivery') {
        $deliveryCount++;
    }
    else {
        $pickupCount++;
    }
    $totalCost += floatval($v['total_cost']);
}

echo json_encode([
    'success' => true,
    'ventas' => $ventas,
    'pickup_count' => $pickupCount,
    'delivery_count' => $deliveryCount,
    'total_orders' => count($ventas),
    'total_cost' => round($totalCost, 2)
]);

mysqli_close($conn);
?>