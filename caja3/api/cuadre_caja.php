<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) throw new Exception("Error de conexión: " . $conn->connect_error);

    // Obtener parámetros
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

    // 1. Resumen de movimientos
    $sql_resumen = "
    SELECT 
        DATE(fecha_movimiento) as fecha,
        tipo,
        COUNT(*) as cantidad,
        SUM(monto) as total
    FROM caja_movimientos
    WHERE DATE(fecha_movimiento) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    GROUP BY DATE(fecha_movimiento), tipo
    ORDER BY fecha DESC, tipo
    ";

    $result_resumen = $conn->query($sql_resumen);
    $resumen = [];
    while ($row = $result_resumen->fetch_assoc()) {
        $resumen[] = $row;
    }

    // 2. Validar integridad de saldos
    $sql_validar = "
    SELECT 
        id,
        fecha_movimiento,
        tipo,
        monto,
        saldo_anterior,
        saldo_nuevo,
        (saldo_anterior + IF(tipo='ingreso', monto, -monto)) as saldo_calculado,
        IF((saldo_anterior + IF(tipo='ingreso', monto, -monto)) = saldo_nuevo, 'OK', 'ERROR') as validacion
    FROM caja_movimientos
    WHERE DATE(fecha_movimiento) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    ORDER BY fecha_movimiento ASC
    ";

    $result_validar = $conn->query($sql_validar);
    $errores = [];
    while ($row = $result_validar->fetch_assoc()) {
        if ($row['validacion'] === 'ERROR') {
            $errores[] = $row;
        }
    }

    // 3. Saldo inicial y final
    $sql_saldo_inicial = "
    SELECT saldo_nuevo 
    FROM caja_movimientos 
    WHERE DATE(fecha_movimiento) = '$fecha_inicio'
    ORDER BY fecha_movimiento ASC 
    LIMIT 1
    ";
    $result_inicial = $conn->query($sql_saldo_inicial);
    $saldo_inicial = $result_inicial->fetch_assoc()['saldo_nuevo'] ?? 0;

    $sql_saldo_final = "
    SELECT saldo_nuevo 
    FROM caja_movimientos 
    WHERE DATE(fecha_movimiento) <= '$fecha_fin'
    ORDER BY fecha_movimiento DESC 
    LIMIT 1
    ";
    $result_final = $conn->query($sql_saldo_final);
    $saldo_final = $result_final->fetch_assoc()['saldo_nuevo'] ?? 0;

    // 4. Totales por tipo
    $sql_totales = "
    SELECT 
        tipo,
        SUM(monto) as total,
        COUNT(*) as cantidad
    FROM caja_movimientos
    WHERE DATE(fecha_movimiento) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    GROUP BY tipo
    ";

    $result_totales = $conn->query($sql_totales);
    $totales = [];
    while ($row = $result_totales->fetch_assoc()) {
        $totales[$row['tipo']] = $row;
    }

    // 5. Movimientos sin referencia de pedido
    $sql_sin_ref = "
    SELECT 
        id,
        fecha_movimiento,
        tipo,
        monto,
        motivo,
        usuario
    FROM caja_movimientos
    WHERE DATE(fecha_movimiento) BETWEEN '$fecha_inicio' AND '$fecha_fin'
    AND order_reference IS NULL
    AND tipo = 'ingreso'
    ORDER BY fecha_movimiento DESC
    ";

    $result_sin_ref = $conn->query($sql_sin_ref);
    $sin_referencia = [];
    while ($row = $result_sin_ref->fetch_assoc()) {
        $sin_referencia[] = $row;
    }

    // 6. Cálculo de diferencia
    $total_ingresos = $totales['ingreso']['total'] ?? 0;
    $total_egresos = $totales['retiro']['total'] ?? 0;
    $diferencia = $saldo_final - $saldo_inicial;
    $esperado = $total_ingresos - $total_egresos;
    $discrepancia = $diferencia - $esperado;

    echo json_encode([
        'success' => true,
        'periodo' => [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin
        ],
        'saldos' => [
            'saldo_inicial' => $saldo_inicial,
            'saldo_final' => $saldo_final,
            'diferencia' => $diferencia
        ],
        'totales' => [
            'ingresos' => $total_ingresos,
            'egresos' => $total_egresos,
            'neto' => $total_ingresos - $total_egresos
        ],
        'validacion' => [
            'esperado' => $esperado,
            'real' => $diferencia,
            'discrepancia' => $discrepancia,
            'cuadra' => abs($discrepancia) < 0.01 ? 'SI' : 'NO'
        ],
        'resumen_diario' => $resumen,
        'errores_saldo' => $errores,
        'movimientos_sin_referencia' => $sin_referencia,
        'cantidad_errores' => count($errores)
    ], JSON_PRETTY_PRINT);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
