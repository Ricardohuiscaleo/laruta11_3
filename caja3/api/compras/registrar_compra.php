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

    $input = json_decode(file_get_contents('php://input'), true);

    $pdo->beginTransaction();

    // Insertar compra
    $stmt = $pdo->prepare("INSERT INTO compras (
        fecha_compra, proveedor, tipo_compra, monto_total, 
        metodo_pago, estado, notas, usuario
    ) VALUES (?, ?, ?, ?, ?, 'pagado', ?, ?)");

    $stmt->execute([
        $input['fecha_compra'],
        $input['proveedor'],
        $input['tipo_compra'],
        $input['monto_total'],
        $input['metodo_pago'],
        $input['notas'] ?? null,
        $input['usuario'] ?? 'Admin'
    ]);

    $compra_id = $pdo->lastInsertId();

    // Insertar items con snapshot de inventario
    $stmt = $pdo->prepare("INSERT INTO compras_detalle (
        compra_id, ingrediente_id, product_id, item_type, nombre_item, cantidad, 
        unidad, precio_unitario, subtotal, stock_antes, stock_despues
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($input['items'] as $item) {
        $stock_antes = null;
        $stock_despues = null;
        $item_type = $item['item_type'] ?? 'ingredient';

        // Obtener stock actual ANTES de actualizar
        if ($item_type === 'ingredient' && $item['ingrediente_id']) {
            $stockStmt = $pdo->prepare("SELECT current_stock FROM ingredients WHERE id = ?");
            $stockStmt->execute([$item['ingrediente_id']]);
            $stock_antes = $stockStmt->fetchColumn();
            $stock_despues = $stock_antes + $item['cantidad'];
        } elseif ($item_type === 'product' && $item['product_id']) {
            $stockStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stockStmt->execute([$item['product_id']]);
            $stock_antes = $stockStmt->fetchColumn();
            $stock_despues = $stock_antes + $item['cantidad'];
        }

        $stmt->execute([
            $compra_id,
            $item['ingrediente_id'] ?: null,
            $item['product_id'] ?: null,
            $item_type,
            $item['nombre_item'],
            $item['cantidad'],
            $item['unidad'],
            $item['precio_unitario'],
            $item['subtotal'],
            $stock_antes,
            $stock_despues
        ]);

        // Actualizar inventario según el tipo
        if ($item_type === 'ingredient' && $item['ingrediente_id']) {
            $updateStmt = $pdo->prepare("UPDATE ingredients 
                SET current_stock = current_stock + ?,
                    cost_per_unit = ?,
                    supplier = CASE WHEN ? != '' THEN ? ELSE supplier END
                WHERE id = ?");
            $updateStmt->execute([
                $item['cantidad'],
                $item['precio_unitario'],
                $input['proveedor'] ?? '',
                $input['proveedor'] ?? '',
                $item['ingrediente_id']
            ]);
        } elseif ($item_type === 'product' && $item['product_id']) {
            $updateStmt = $pdo->prepare("UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE id = ?");
            $updateStmt->execute([$item['cantidad'], $item['product_id']]);
        }
    }

    // Actualizar capital de trabajo
    $fecha = date('Y-m-d', strtotime($input['fecha_compra']));
    $updateCapital = $pdo->prepare("INSERT INTO capital_trabajo (
        fecha, egresos_compras, saldo_final
    ) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        egresos_compras = egresos_compras + VALUES(egresos_compras),
        saldo_final = saldo_inicial + ingresos_ventas - (egresos_compras + VALUES(egresos_compras)) - egresos_gastos");
    
    $updateCapital->execute([$fecha, $input['monto_total'], 0]);

    $pdo->commit();

    // Obtener detalles con snapshots
    $detalles = $pdo->prepare("SELECT cd.*, i.name as ingrediente_nombre 
        FROM compras_detalle cd 
        LEFT JOIN ingredients i ON cd.ingrediente_id = i.id 
        WHERE cd.compra_id = ?");
    $detalles->execute([$compra_id]);
    $items_detalle = $detalles->fetchAll(PDO::FETCH_ASSOC);

    // Calcular saldo después de la compra
    $stmt = $pdo->query("SELECT 
        (SELECT SUM(installment_amount - COALESCE(delivery_fee, 0)) 
         FROM tuu_orders 
         WHERE payment_status = 'paid' 
         AND MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
         AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)) as ventas_anterior,
        (SELECT SUM(installment_amount - COALESCE(delivery_fee, 0)) 
         FROM tuu_orders 
         WHERE payment_status = 'paid' 
         AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
         AND YEAR(created_at) = YEAR(CURRENT_DATE())) as ventas_actual,
        (SELECT SUM(monto_total) FROM compras 
         WHERE MONTH(fecha_compra) = MONTH(CURRENT_DATE()) 
         AND YEAR(fecha_compra) = YEAR(CURRENT_DATE())) as compras");
    $saldos = $stmt->fetch(PDO::FETCH_ASSOC);
    $ventas_anterior = (float)($saldos['ventas_anterior'] ?? 0);
    $ventas_actual = (float)($saldos['ventas_actual'] ?? 0);
    $lastMonth = date('Y-m', strtotime('-1 month'));
    if ($lastMonth === '2025-10') $ventas_anterior += 695433;
    $saldo_nuevo = $ventas_anterior + $ventas_actual - 1590000 - (float)($saldos['compras'] ?? 0);

    echo json_encode([
        'success' => true,
        'compra_id' => $compra_id,
        'message' => 'Compra registrada correctamente',
        'items_detalle' => $items_detalle,
        'saldo_nuevo' => $saldo_nuevo
    ]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
