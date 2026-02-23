<?php
set_time_limit(60);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$config_paths = [
    __DIR__ . '/config.php',
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

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    $currentDay = (int)$now->format('d');
    
    // Determinar día del turno actual
    $shiftToday = clone $now;
    if ($currentHour >= 0 && $currentHour < 4) {
        $shiftToday->modify('-1 day');
    }
    
    $currentYear = $shiftToday->format('Y');
    $currentMonth = $shiftToday->format('m');
    
    // Calcular rango UTC del mes completo considerando turnos
    $firstShiftStart = "$currentYear-$currentMonth-01 17:00:00";
    $firstShiftStartUTC = date('Y-m-d H:i:s', strtotime($firstShiftStart . ' +3 hours'));
    
    $endOfMonth = new DateTime("$currentYear-$currentMonth-01");
    $endOfMonth->modify('last day of this month');
    $lastDay = $endOfMonth->format('Y-m-d');
    $dayAfter = date('Y-m-d', strtotime($lastDay . ' +1 day'));
    $lastShiftEnd = "$dayAfter 04:00:00";
    $lastShiftEndUTC = date('Y-m-d H:i:s', strtotime($lastShiftEnd . ' +3 hours'));
    
    // ========== TARJETA 1: VENTAS ==========
    $projectionUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api/get_smart_projection_shifts.php";
    $projectionData = @file_get_contents($projectionUrl);
    $projection = json_decode($projectionData, true);
    
    $ventasReal = $projection['data']['totalReal'] ?? 0;
    $ventasProyectado = $projection['data']['totalMonthProjection'] ?? 0;
    $avgByWeekday = $projection['data']['avgByWeekday'] ?? [];
    
    // Obtener órdenes del mes con lógica de turnos
    $stmtOrdenes = $pdo->prepare("
        SELECT COUNT(*) as total_ordenes, AVG(installment_amount) as ticket_promedio
        FROM tuu_orders 
        WHERE created_at >= ? AND created_at < ? AND payment_status = 'paid'
    ");
    $stmtOrdenes->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $ordenesData = $stmtOrdenes->fetch(PDO::FETCH_ASSOC);
    
    $totalOrdenes = (int)($ordenesData['total_ordenes'] ?? 0);
    $ticketPromedio = (float)($ordenesData['ticket_promedio'] ?? 0);
    
    // Meta mensual (ejemplo: $2.4M)
    $metaMensual = 2400000;
    $porcentajeMeta = $ventasProyectado > 0 ? ($ventasReal / $metaMensual) * 100 : 0;
    $esperadoHoy = ($metaMensual / 30) * $currentDay;
    $porcentajeEsperado = $esperadoHoy > 0 ? ($ventasReal / $esperadoHoy) * 100 : 0;
    
    // ========== TARJETA 2: COMPRAS ==========
    // Usar mes calendario actual, no mes del turno
    $actualCurrentMonth = $now->format('Y-m');
    $stmtCompras = $pdo->prepare("
        SELECT 
            SUM(monto_total) as total_compras,
            COUNT(*) as num_compras
        FROM compras 
        WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ?
    ");
    $stmtCompras->execute([$actualCurrentMonth]);
    $comprasData = $stmtCompras->fetch(PDO::FETCH_ASSOC);
    
    $totalCompras = (float)($comprasData['total_compras'] ?? 0);
    $numCompras = (int)($comprasData['num_compras'] ?? 0);
    
    // Top proveedor
    $stmtProveedores = $pdo->prepare("
        SELECT proveedor
        FROM compras 
        WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ? AND proveedor IS NOT NULL
        GROUP BY proveedor 
        ORDER BY COUNT(*) DESC 
        LIMIT 1
    ");
    $stmtProveedores->execute([$actualCurrentMonth]);
    $topProv = $stmtProveedores->fetch(PDO::FETCH_ASSOC);
    $topProveedor = $topProv['proveedor'] ?? '-';
    
    // ========== TARJETA 3: INVENTARIOS ==========
    // Valor de ingredientes (solo cocina, excluir packaging/limpieza)
    $stmtIngredientes = $pdo->query("
        SELECT 
            SUM(current_stock * cost_per_unit) as valor_ingredientes,
            COUNT(*) as items_ingredientes
        FROM ingredients 
        WHERE is_active = 1
        AND category IN ('Carnes', 'Aves', 'Lácteos', 'Vegetales', 'Salsas', 'Aceites', 'Condimentos', 'Panes', 'Embutidos', 'queso')
    ");
    $ingredientesData = $stmtIngredientes->fetch(PDO::FETCH_ASSOC);
    $valorIngredientes = (float)($ingredientesData['valor_ingredientes'] ?? 0);
    $itemsIngredientes = (int)($ingredientesData['items_ingredientes'] ?? 0);
    
    // Valor de productos sin receta (solo categoría 5: Snacks/Bebidas)
    $stmtProductosSinReceta = $pdo->query("
        SELECT 
            SUM(p.stock_quantity * p.cost_price) as valor_productos,
            COUNT(*) as items_productos
        FROM products p
        LEFT JOIN product_recipes r ON p.id = r.product_id
        WHERE p.is_active = 1 
        AND r.id IS NULL
        AND p.cost_price > 0
        AND p.category_id = 5
    ");
    $productosData = $stmtProductosSinReceta->fetch(PDO::FETCH_ASSOC);
    $valorProductos = (float)($productosData['valor_productos'] ?? 0);
    $itemsProductos = (int)($productosData['items_productos'] ?? 0);
    
    // Total inventario
    $valorInventario = $valorIngredientes + $valorProductos;
    $totalItems = $itemsIngredientes + $itemsProductos;
    
    // Item con más dinero estancado en inventario
    $stmtTopInventario = $pdo->query("
        SELECT 
            name as item_name,
            (current_stock * cost_per_unit) as valor_stock
        FROM ingredients
        WHERE is_active = 1
        AND category IN ('Carnes', 'Aves', 'Lácteos', 'Vegetales', 'Salsas', 'Aceites', 'Condimentos', 'Panes', 'Embutidos', 'queso')
        
        UNION ALL
        
        SELECT 
            p.name as item_name,
            (p.stock_quantity * p.cost_price) as valor_stock
        FROM products p
        LEFT JOIN product_recipes r ON p.id = r.product_id
        WHERE p.is_active = 1
        AND r.id IS NULL
        AND p.cost_price > 0
        AND p.category_id = 5
        
        ORDER BY valor_stock DESC
        LIMIT 1
    ");
    $topInv = $stmtTopInventario->fetch(PDO::FETCH_ASSOC);
    $topInventarioItem = $topInv['item_name'] ?? '-';
    $topInventarioValor = (float)($topInv['valor_stock'] ?? 0);
    
    // Producto más vendido (por ingresos) para tarjeta de Ventas con lógica de turnos
    $stmtTopProducto = $pdo->prepare("
        SELECT oi.product_id, oi.product_name, SUM(oi.subtotal) as ingresos
        FROM tuu_order_items oi
        INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
        WHERE o.created_at >= ? AND o.created_at < ? AND o.payment_status = 'paid'
        GROUP BY oi.product_id, oi.product_name
        ORDER BY ingresos DESC
        LIMIT 1
    ");
    $stmtTopProducto->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $topProd = $stmtTopProducto->fetch(PDO::FETCH_ASSOC);
    $masVendido = $topProd['product_name'] ?? '-';
    $masVendidoId = $topProd['product_id'] ?? null;
    $masVendidoIngresos = (float)($topProd['ingresos'] ?? 0);
    
    // Calcular rotación de inventario (Costo Ventas Mes / Valor Inventario) con lógica de turnos
    $stmtCostoVentas = $pdo->prepare("
        SELECT SUM(oi.quantity * COALESCE(p.cost_price, 0)) as costo_ventas
        FROM tuu_order_items oi
        JOIN tuu_orders o ON oi.order_reference = o.order_number
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.created_at >= ? AND o.created_at < ?
        AND o.payment_status = 'paid'
        AND o.order_number NOT LIKE 'RL6-%'
    ");
    $stmtCostoVentas->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $costoVentasData = $stmtCostoVentas->fetch(PDO::FETCH_ASSOC);
    $costoVentasMes = (float)($costoVentasData['costo_ventas'] ?? 0);
    
    // Rotación = (Costo Ventas / Valor Inventario)
    $rotacionInventario = $valorInventario > 0 ? ($costoVentasMes / $valorInventario) : 0;
    
    // ========== TARJETA 4: PLAN DE COMPRAS ==========
    // Usar API de plan de compras
    $planUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api/get_purchase_plan.php?days=3";
    $planData = @file_get_contents($planUrl);
    $plan = json_decode($planData, true);
    
    $itemsReposicion = 0;
    $costoReposicion = 0;
    $itemsUrgentes = 0;
    $diasStock = 3;
    
    $itemsCriticos = 0;
    if ($plan && $plan['success']) {
        $itemsReposicion = count($plan['data']['purchase_list'] ?? []);
        $costoReposicion = (float)($plan['data']['total_cost'] ?? 0);
        
        // Contar urgentes y críticos
        foreach ($plan['data']['purchase_list'] ?? [] as $item) {
            $stock = floatval($item['current_stock'] ?? 0);
            $needed = floatval($item['needed'] ?? 0);
            if ($needed > 0) {
                if ($stock < ($needed * 0.2)) {
                    $itemsUrgentes++;
                }
                if ($stock < ($needed * 0.5)) {
                    $itemsCriticos++;
                }
            }
        }
    }
    
    // ========== MARGEN OPERATIVO (Ventas - Compras) ==========
    $margenOperativo = $ventasReal - $totalCompras;
    $porcentajeMargen = $ventasReal > 0 ? ($margenOperativo / $ventasReal) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            // TARJETA 1: VENTAS
            'ventas' => [
                'real' => $ventasReal,
                'proyectado' => $ventasProyectado,
                'meta_mensual' => $metaMensual,
                'porcentaje_meta' => round($porcentajeMeta, 1),
                'esperado_hoy' => $esperadoHoy,
                'porcentaje_esperado' => round($porcentajeEsperado, 1),
                'ticket_promedio' => round($ticketPromedio, 0),
                'total_ordenes' => $totalOrdenes,
                'dias_transcurridos' => $currentDay,
                'shift_logic' => true,
                'avg_by_weekday' => $avgByWeekday
            ],
            
            // TARJETA 2: COMPRAS
            'compras' => [
                'total_mes' => $totalCompras,
                'numero_compras' => $numCompras,
                'items_criticos' => $itemsCriticos,
                'top_proveedor' => $topProveedor
            ],
            
            // TARJETA 3: INVENTARIOS
            'inventarios' => [
                'valor_total' => $valorInventario,
                'items_activos' => $totalItems,
                'top_inventario' => $topInventarioItem,
                'top_inventario_valor' => $topInventarioValor,
                'mas_vendido' => $masVendido,
                'mas_vendido_id' => $masVendidoId,
                'mas_vendido_ingresos' => $masVendidoIngresos,
                'rotacion' => $rotacionInventario
            ],
            
            // TARJETA 4: PLAN DE COMPRAS
            'plan_compras' => [
                'items_reposicion' => $itemsReposicion,
                'costo_estimado' => $costoReposicion,
                'items_urgentes' => $itemsUrgentes,
                'dias_stock' => $diasStock
            ],
            
            // MARGEN OPERATIVO
            'margen_operativo' => [
                'ventas' => $ventasReal,
                'compras' => $totalCompras,
                'margen' => $margenOperativo,
                'porcentaje' => round($porcentajeMargen, 1),
                'shift_logic' => true
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
