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
    __DIR__ . '/../../../../config.php'
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

    // Calcular saldo base (mes anterior)
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $stmt = $pdo->prepare("SELECT 
        SUM(installment_amount - COALESCE(delivery_fee, 0)) as monto
        FROM tuu_orders 
        WHERE payment_status = 'paid' 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$lastMonth]);
    $ventas = $stmt->fetch(PDO::FETCH_ASSOC);
    $montoVentasAnterior = $ventas ? (float)$ventas['monto'] : 0;
    
    if ($lastMonth === '2025-10') {
        $montoVentasAnterior += 695433;
    }

    // Ventas mes actual
    $currentMonth = date('Y-m');
    $stmt = $pdo->prepare("SELECT 
        MAX(created_at) as ultima_venta,
        SUM(installment_amount - COALESCE(delivery_fee, 0)) as monto
        FROM tuu_orders 
        WHERE payment_status = 'paid' 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$currentMonth]);
    $ventasActuales = $stmt->fetch(PDO::FETCH_ASSOC);
    $montoVentasActuales = $ventasActuales ? (float)$ventasActuales['monto'] : 0;
    $ultimaVenta = $ventasActuales['ultima_venta'] ?? null;

    // Días transcurridos del mes
    $diasMes = (int)date('d');

    // Construir movimientos: primero mes anterior (base), luego actual
    $movimientos = [];
    
    // 1. Ventas mes anterior (base del saldo)
    if ($montoVentasAnterior > 0) {
        $movimientos[] = [
            'fecha' => $lastMonth . '-01',
            'tipo' => 'ingreso',
            'concepto' => 'Ventas ' . date('F Y', strtotime($lastMonth)),
            'monto' => $montoVentasAnterior,
            'saldo_resultante' => $montoVentasAnterior,
            'orden' => 3
        ];
    }

    // 2. Ventas mes actual
    if ($montoVentasActuales > 0) {
        $movimientos[] = [
            'fecha' => $currentMonth . '-01',
            'tipo' => 'ingreso',
            'concepto' => 'Ventas ' . date('F Y', strtotime($currentMonth)),
            'monto' => $montoVentasActuales,
            'saldo_resultante' => $montoVentasAnterior + $montoVentasActuales,
            'dias_transcurridos' => $diasMes,
            'ultima_venta' => $ultimaVenta,
            'orden' => 0
        ];
    }

    // 3. Sueldos
    $saldoActual = $montoVentasAnterior + $montoVentasActuales - 1590000;
    $movimientos[] = [
        'fecha' => $currentMonth . '-01',
        'tipo' => 'egreso',
        'concepto' => 'Sueldos Mensuales',
        'monto' => 1590000,
        'saldo_resultante' => $saldoActual,
        'orden' => 1
    ];

    // 4. Compras (mes anterior y actual)
    $stmt = $pdo->prepare("SELECT fecha_compra, proveedor, monto_total 
        FROM compras 
        WHERE DATE_FORMAT(fecha_compra, '%Y-%m') IN (?, ?)
        ORDER BY fecha_compra DESC");
    $stmt->execute([$lastMonth, $currentMonth]);
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($compras as $compra) {
        $saldoActual -= $compra['monto_total'];
        $movimientos[] = [
            'fecha' => $compra['fecha_compra'],
            'tipo' => 'egreso',
            'concepto' => 'Compra - ' . $compra['proveedor'],
            'monto' => (float)$compra['monto_total'],
            'saldo_resultante' => $saldoActual,
            'orden' => 2
        ];
    }

    // Ordenar por campo 'orden'
    usort($movimientos, function($a, $b) {
        return $a['orden'] - $b['orden'];
    });

    // Remover campo 'orden' antes de enviar
    foreach ($movimientos as &$mov) {
        unset($mov['orden']);
    }

    echo json_encode([
        'success' => true,
        'movimientos' => $movimientos,
        'saldo_actual' => $montoVentasAnterior + $montoVentasActuales - 1590000 - array_sum(array_column($compras, 'monto_total'))
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
