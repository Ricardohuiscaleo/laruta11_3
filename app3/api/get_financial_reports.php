<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

$config = include $configPath;

try {
    $conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );
    
    if (!$conn) {
        throw new Exception('Error de conexión: ' . mysqli_connect_error());
    }
    
    mysqli_set_charset($conn, 'utf8mb4');
    
    // GASTOS FIJOS MENSUALES
    $gastos_fijos = [
        'sueldos' => 1500000,
        'arriendo' => 0,
        'servicios' => 0,
        'otros' => 0
    ];
    $total_gastos_fijos = array_sum($gastos_fijos);
    
    // 1. RESUMEN HOY
    $hoy = date('Y-m-d');
    $sql_hoy = "SELECT 
        COUNT(DISTINCT id) as pedidos,
        SUM(subtotal) as ventas,
        SUM(delivery_fee) as delivery,
        SUM(subtotal) + SUM(delivery_fee) as total
    FROM tuu_orders
    WHERE DATE(created_at) = '$hoy' AND payment_status = 'paid'";
    
    $result_hoy = mysqli_query($conn, $sql_hoy);
    $hoy_data = mysqli_fetch_assoc($result_hoy);
    
    // 2. COMPARATIVA MENSUAL
    $sql_meses = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mes,
        COUNT(DISTINCT id) as pedidos,
        SUM(subtotal) as ventas,
        SUM(delivery_fee) as delivery,
        SUM(subtotal) + SUM(delivery_fee) as total,
        AVG(subtotal) as ticket_promedio
    FROM tuu_orders
    WHERE payment_status = 'paid'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12";
    
    $result_meses = mysqli_query($conn, $sql_meses);
    $meses_data = [];
    while ($row = mysqli_fetch_assoc($result_meses)) {
        $meses_data[] = $row;
    }
    
    // 3. CALCULAR PÉRDIDA/GANANCIA
    $sql_rentabilidad = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mes,
        SUM(subtotal) as ventas,
        COUNT(DISTINCT id) as pedidos,
        ROUND(SUM(subtotal) * 0.40, 2) as costo_estimado_40pct,
        ROUND(SUM(subtotal) - (SUM(subtotal) * 0.40), 2) as utilidad_bruta
    FROM tuu_orders
    WHERE payment_status = 'paid'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12";
    
    $result_rent = mysqli_query($conn, $sql_rentabilidad);
    $rentabilidad = [];
    while ($row = mysqli_fetch_assoc($result_rent)) {
        $row['gastos_fijos'] = $total_gastos_fijos;
        $row['utilidad_neta'] = $row['utilidad_bruta'] - $total_gastos_fijos;
        $row['estado'] = $row['utilidad_neta'] >= 0 ? '✅ GANANCIA' : '❌ PÉRDIDA';
        $rentabilidad[] = $row;
    }
    
    // 4. PUNTO DE EQUILIBRIO
    $punto_equilibrio = null;
    if (!empty($meses_data)) {
        $mes_actual = $meses_data[0];
        $dias_transcurridos = (int)date('d');
        $dias_totales = (int)date('t');
        $dias_restantes = $dias_totales - $dias_transcurridos;
        
        $promedio_diario = $mes_actual['total'] / $dias_transcurridos;
        $proyeccion_mes = $promedio_diario * $dias_totales;
        
        $ventas_necesarias = $total_gastos_fijos / 0.60;
        $dias_para_equilibrio = ceil($ventas_necesarias / $promedio_diario);
        
        $punto_equilibrio = [
            'mes_actual' => $mes_actual['mes'],
            'dias_transcurridos' => $dias_transcurridos,
            'dias_restantes' => $dias_restantes,
            'ventas_actuales' => $mes_actual['total'],
            'promedio_diario' => round($promedio_diario, 2),
            'proyeccion_mes' => round($proyeccion_mes, 2),
            'gastos_fijos_mes' => $total_gastos_fijos,
            'ventas_necesarias_equilibrio' => round($ventas_necesarias, 2),
            'dias_para_equilibrio' => $dias_para_equilibrio,
            'estado_equilibrio' => $proyeccion_mes >= $ventas_necesarias ? '✅ ALCANZABLE' : '❌ NO ALCANZABLE'
        ];
    }
    
    // 5. COMPARATIVA: ¿MEJOR O PEOR?
    $comparativa = [];
    if (count($meses_data) >= 2) {
        $mes_actual = $meses_data[0];
        $mes_anterior = $meses_data[1];
        
        $variacion_ventas = (($mes_actual['total'] - $mes_anterior['total']) / $mes_anterior['total']) * 100;
        $variacion_pedidos = (($mes_actual['pedidos'] - $mes_anterior['pedidos']) / $mes_anterior['pedidos']) * 100;
        $variacion_ticket = (($mes_actual['ticket_promedio'] - $mes_anterior['ticket_promedio']) / $mes_anterior['ticket_promedio']) * 100;
        
        $comparativa = [
            'mes_actual' => $mes_actual['mes'],
            'mes_anterior' => $mes_anterior['mes'],
            'variacion_ventas_pct' => round($variacion_ventas, 1),
            'variacion_pedidos_pct' => round($variacion_pedidos, 1),
            'variacion_ticket_pct' => round($variacion_ticket, 1),
            'tendencia_ventas' => $variacion_ventas >= 0 ? '📈 MEJOR' : '📉 PEOR',
            'tendencia_pedidos' => $variacion_pedidos >= 0 ? '📈 MEJOR' : '📉 PEOR',
            'tendencia_ticket' => $variacion_ticket >= 0 ? '📈 MEJOR' : '📉 PEOR'
        ];
    }
    
    // 6. CANALES DE VENTA
    $sql_canales = "SELECT 
        COALESCE(payment_method, 'Sin especificar') as canal,
        COUNT(DISTINCT id) as pedidos,
        SUM(subtotal) as ventas,
        AVG(subtotal) as ticket_promedio
    FROM tuu_orders
    WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY payment_method
    ORDER BY ventas DESC";
    
    $result_canales = mysqli_query($conn, $sql_canales);
    $canales = [];
    while ($row = mysqli_fetch_assoc($result_canales)) {
        $canales[] = $row;
    }
    
    // 7. PRODUCTOS TOP
    $sql_productos = "SELECT 
        product_name as producto,
        COUNT(*) as veces_vendido,
        SUM(product_price) as ingresos,
        AVG(product_price) as precio_promedio
    FROM tuu_orders
    WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY product_name
    ORDER BY ingresos DESC
    LIMIT 10";
    
    $result_productos = mysqli_query($conn, $sql_productos);
    $productos = [];
    while ($row = mysqli_fetch_assoc($result_productos)) {
        $productos[] = $row;
    }
    
    // Respuesta final
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'hoy' => $hoy_data,
        'meses' => $meses_data,
        'rentabilidad' => $rentabilidad,
        'punto_equilibrio' => $punto_equilibrio,
        'comparativa' => $comparativa,
        'canales' => $canales,
        'productos_top' => $productos,
        'gastos_fijos' => $gastos_fijos,
        'total_gastos_fijos' => $total_gastos_fijos
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
