<?php
header('Content-Type: application/json');
$config = null;
foreach ([__DIR__ . '/../../public/config.php', __DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../../config.php', __DIR__ . '/../../../../config.php'] as $p) {
    if (file_exists($p)) {
        $config = require_once $p;
        break;
    }
}
if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

$mes = $_GET['mes'] ?? date('Y-m');
$year = explode('-', $mes)[0];
$month = explode('-', $mes)[1];

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Calcular fechas del turno para el mes (igual que dashboard)
    $firstShiftStart = "$year-$month-01 17:00:00";
    $firstShiftStartUTC = date('Y-m-d H:i:s', strtotime($firstShiftStart . ' +3 hours'));

    $endOfMonth = new DateTime("$year-$month-01");
    $endOfMonth->modify('last day of this month');
    $lastDay = $endOfMonth->format('Y-m-d');
    $dayAfter = date('Y-m-d', strtotime($lastDay . ' +1 day'));
    $lastShiftEnd = "$dayAfter 04:00:00";
    $lastShiftEndUTC = date('Y-m-d H:i:s', strtotime($lastShiftEnd . ' +3 hours'));

    // 1. Total Ventas (TUU) - Fetching directly from the same exact API as the Dashboard
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Check if we are querying the current month
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentMonth = $now->format('Y-m');
    $queryMonth = "$year-$month";

    $ventas = 0;

    if ($queryMonth === $currentMonth) {
        $projectionUrl = $protocol . $host . "/api/get_smart_projection_shifts.php";
        $projectionDataRaw = @file_get_contents($projectionUrl);
        if ($projectionDataRaw) {
            $projection = json_decode($projectionDataRaw, true);
            $ventas = (float)($projection['data']['totalReal'] ?? 0);
        }
    }
    else {
        // Para meses históricos, usamos la sumatoria robusta anterior
        $stmtVentas = $pdo->prepare("
            SELECT COALESCE(SUM(COALESCE(tuu_amount, installment_amount, product_price) - COALESCE(delivery_fee, 0)), 0) as total_ventas
            FROM tuu_orders 
            WHERE created_at >= ? AND created_at < ? AND payment_status = 'paid' AND order_number NOT LIKE 'RL6-%'
        ");
        $stmtVentas->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
        $ventas = (float)($stmtVentas->fetchColumn() ?? 0);
    }

    // 2. Total Compras
    $stmtCompras = $pdo->prepare("
        SELECT COALESCE(SUM(monto_total), 0) as total_compras
        FROM compras 
        WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ?
    ");
    $stmtCompras->execute([$mes]);
    $compras = (float)($stmtCompras->fetchColumn() ?? 0);

    // 3. Total Sueldos Base (solo ruta11)
    $stmtSueldos = $pdo->query("
        SELECT COALESCE(SUM(sueldo_base_cajero + sueldo_base_planchero + sueldo_base_admin), 0) as total_sueldos
        FROM personal 
        WHERE (rol NOT LIKE '%seguridad%' OR rol IS NULL) AND nombre != 'Yojhans' AND activo = 1
    ");
    $sueldos = (float)($stmtSueldos->fetchColumn() ?? 0);

    $liquidez = $ventas - $compras - $sueldos;

    echo json_encode([
        'success' => true,
        'data' => [
            'ventas' => $ventas,
            'compras' => $compras,
            'sueldos' => $sueldos,
            'liquidez' => $liquidez
        ]
    ]);
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>