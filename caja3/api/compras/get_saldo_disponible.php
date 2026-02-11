<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Ventas del mes ANTERIOR con lógica de turno (17:30-04:00)
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $lastMonthDate = new DateTime('first day of last month', new DateTimeZone('America/Santiago'));
    $lastMonthEnd = new DateTime('last day of last month', new DateTimeZone('America/Santiago'));
    
    $start_date_chile = $lastMonthDate->format('Y-m-d') . ' 17:30:00';
    $end_date_chile = $lastMonthEnd->format('Y-m-d') . ' 04:00:00';
    $end_date_chile = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +1 day'));
    
    $start_date_utc = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
    $end_date_utc = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
    
    $stmt = $pdo->prepare("SELECT SUM(installment_amount - COALESCE(delivery_fee, 0)) as total_ventas 
        FROM tuu_orders 
        WHERE payment_status = 'paid' 
        AND created_at >= ? 
        AND created_at < ?");
    $stmt->execute([$start_date_utc, $end_date_utc]);
    $ventas = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalVentas = $ventas ? (float)$ventas['total_ventas'] : 0;

    // 🔥 INYECCIÓN HARDCODEADA OCTUBRE 2025 - TPV FALTANTE
    $lastMonth = date('Y-m', strtotime('-1 month'));
    if ($lastMonth === '2025-10') {
        $totalVentas += 695433;
    }

    // Ventas del mes ACTUAL con lógica de turno (desde día 1 a las 17:30 hasta ahora)
    $currentMonthDate = new DateTime('first day of this month', new DateTimeZone('America/Santiago'));
    $start_current_chile = $currentMonthDate->format('Y-m-d') . ' 17:30:00';
    $end_current_chile = $now->format('Y-m-d H:i:s');
    
    $start_current_utc = date('Y-m-d H:i:s', strtotime($start_current_chile . ' +3 hours'));
    $end_current_utc = date('Y-m-d H:i:s', strtotime($end_current_chile . ' +3 hours'));
    
    $stmt = $pdo->prepare("SELECT SUM(installment_amount - COALESCE(delivery_fee, 0)) as total_ventas_actuales 
        FROM tuu_orders 
        WHERE payment_status = 'paid' 
        AND created_at >= ? 
        AND created_at < ?");
    $stmt->execute([$start_current_utc, $end_current_utc]);
    $ventasActuales = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalVentasActuales = $ventasActuales ? (float)$ventasActuales['total_ventas_actuales'] : 0;

    // Sueldos mensuales fijos
    $sueldos = 1590000;

    // Compras (solo mes actual)
    $currentMonth = date('Y-m');
    $stmt = $pdo->prepare("SELECT SUM(monto_total) as total_compras 
        FROM compras 
        WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $compras = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCompras = $compras ? (float)$compras['total_compras'] : 0;

    $saldoDisponible = $totalVentas + $totalVentasActuales - $sueldos - $totalCompras;

    echo json_encode([
        'success' => true,
        'saldo_disponible' => $saldoDisponible,
        'ventas_mes_anterior' => $totalVentas,
        'ventas_mes_actual' => $totalVentasActuales,
        'sueldos' => $sueldos,
        'compras_mes' => $totalCompras
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
