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

    // 1. Total Ventas (TUU) - Copiando la lógica exacta de get_sales_analytics.php que alimenta la tarjeta principal del Dashboard
    $revenue_sql = "SELECT SUM(o.installment_amount) as total_revenue
                    FROM tuu_orders o
                    WHERE o.created_at >= ? AND o.created_at < ?
                    AND o.payment_status = 'paid'";

    $revenue_stmt = $pdo->prepare($revenue_sql);
    $revenue_stmt->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $revenue_result = $revenue_stmt->fetch(PDO::FETCH_ASSOC);
    $total_revenue = floatval($revenue_result['total_revenue'] ?? 0);

    $delivery_sql = "SELECT SUM(o.delivery_fee) as total_delivery
                     FROM tuu_orders o
                     WHERE o.created_at >= ? AND o.created_at < ?
                     AND o.payment_status = 'paid'";

    $delivery_stmt = $pdo->prepare($delivery_sql);
    $delivery_stmt->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $delivery_result = $delivery_stmt->fetch(PDO::FETCH_ASSOC);
    $total_delivery = floatval($delivery_result['total_delivery'] ?? 0);

    $ventas = $total_revenue - $total_delivery;

    // 2. Total Compras
    $stmtCompras = $pdo->prepare("
        SELECT COALESCE(SUM(monto_total), 0) as total_compras
        FROM compras 
        WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ?
    ");
    $stmtCompras->execute([$mes]);
    $compras = (float)($stmtCompras->fetchColumn() ?? 0);

    // 3. Total Sueldos Base (solo ruta11, lógica exacta de Dashboard get_dashboard_cards.php)
    $stmtSueldos = $pdo->query("
        SELECT COALESCE(SUM(sueldo_base_cajero + sueldo_base_planchero + sueldo_base_admin), 0) as total_sueldos
        FROM personal 
        WHERE rol != 'seguridad' OR rol IS NULL
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