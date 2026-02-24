<?php
/**
 * Telegram Reporting Script for Inventory
 * Designed to be run via Cronjob
 */

$config = require_once __DIR__ . '/../config.php';
$pdo = require_once __DIR__ . '/db_connect.php';

$token = $config['telegram_token'];
$chatId = $config['telegram_chat_id'];

if ($token === 'YOUR_BOT_TOKEN_HERE' || $chatId === 'YOUR_CHAT_ID_HERE') {
    error_log("Telegram reporting skipped: Credentials not configured.");
    exit;
}

try {
    // 1. Rango de fechas (Hoy)
    $startDate = date('Y-m-d 00:00:00');
    $endDate = date('Y-m-d 23:59:59');

    // 2. Ventas del día
    $salesSql = "SELECT COUNT(*) as count, SUM(installment_amount) as total 
                 FROM tuu_orders 
                 WHERE created_at >= ? AND created_at < ? 
                 AND payment_status = 'paid'
                 AND order_number NOT LIKE 'RL6-%'";
    $stmt = $pdo->prepare($salesSql);
    $stmt->execute([$startDate, $endDate]);
    $salesStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Consumo de ingredientes del día (Lógica de get_sales_detail.php)
    $ordersSql = "SELECT id, order_number FROM tuu_orders 
                  WHERE created_at >= ? AND created_at < ? 
                  AND payment_status = 'paid'
                  AND order_number NOT LIKE 'RL6-%'";
    $stmt = $pdo->prepare($ordersSql);
    $stmt->execute([$startDate, $endDate]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ingredient_consumption = [];
    foreach ($orders as $order) {
        $transSql = "
            SELECT 
                it.quantity, it.ingredient_id, it.product_id,
                COALESCE(i.name, p.name) as name,
                COALESCE(it.unit, i.unit, 'unidad') as unit,
                i.current_stock as ing_stock,
                p.stock_quantity as prod_stock
            FROM inventory_transactions it
            LEFT JOIN ingredients i ON it.ingredient_id = i.id
            LEFT JOIN products p ON it.product_id = p.id
            WHERE it.order_reference = ?
        ";
        $transStmt = $pdo->prepare($transSql);
        $transStmt->execute([$order['order_number']]);
        while ($trans = $transStmt->fetch(PDO::FETCH_ASSOC)) {
            $qtyUsed = abs(floatval($trans['quantity']));
            $unit = $trans['unit'];
            if ($unit === 'g' && $qtyUsed < 1)
                $qtyUsed *= 1000;
            elseif ($unit === 'kg') {
                $qtyUsed *= 1000;
                $unit = 'g';
            }

            $name = $trans['name'];
            if (!isset($ingredient_consumption[$name])) {
                $stock = $trans['ingredient_id'] ? $trans['ing_stock'] : $trans['prod_stock'];
                $ingredient_consumption[$name] = [
                    'name' => $name, 'total' => 0, 'unit' => $unit,
                    'stock_actual' => $stock,
                    'ingredient_id' => $trans['ingredient_id'],
                    'product_id' => $trans['product_id']
                ];
            }
            $ingredient_consumption[$name]['total'] += $qtyUsed;
        }
    }

    // 4. Calcular Max Diarios (Batch)
    $ing_ids = array_filter(array_column($ingredient_consumption, 'ingredient_id'));
    $prod_ids = array_filter(array_column($ingredient_consumption, 'product_id'));
    $max_data_map = [];

    if (!empty($ing_ids) || !empty($prod_ids)) {
        $where_clauses = [];
        $params = [];
        if (!empty($ing_ids)) {
            $where_clauses[] = "ingredient_id IN (" . implode(',', array_fill(0, count($ing_ids), '?')) . ")";
            $params = array_merge($params, array_values($ing_ids));
        }
        if (!empty($prod_ids)) {
            $where_clauses[] = "product_id IN (" . implode(',', array_fill(0, count($prod_ids), '?')) . ")";
            $params = array_merge($params, array_values($prod_ids));
        }
        $where_sql = implode(' OR ', $where_clauses);
        $batchMaxSql = "
            SELECT ingredient_id, product_id, MAX(daily_total) as max_daily
            FROM (
                SELECT ingredient_id, product_id, DATE(created_at) as day, SUM(ABS(quantity)) as daily_total
                FROM inventory_transactions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND transaction_type = 'sale'
                AND ($where_sql)
                GROUP BY ingredient_id, product_id, DATE(created_at)
            ) as daily_usage
            GROUP BY ingredient_id, product_id
        ";
        $batchStmt = $pdo->prepare($batchMaxSql);
        $batchStmt->execute($params);
        while ($row = $batchStmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['ingredient_id'] ? "ing_" . $row['ingredient_id'] : "prod_" . $row['product_id'];
            $max_data_map[$key] = floatval($row['max_daily']);
        }
    }

    // 5. Generar Mensaje
    $critical_items = [];
    $low_items = [];

    foreach ($ingredient_consumption as &$ing) {
        $key = $ing['ingredient_id'] ? "ing_" . $ing['ingredient_id'] : "prod_" . $ing['product_id'];
        $maxDaily = $max_data_map[$key] ?? 0;

        $stock = floatval($ing['stock_actual']);
        if ($ing['unit'] === 'g') {
            if ($stock < 100 && $stock > 0)
                $stock *= 1000;
            if ($maxDaily < 100 && $maxDaily > 0)
                $maxDaily *= 1000;
        }

        $formatVal = function ($val, $unit) {
            return $unit === 'g' && $val >= 1000 ? number_format($val / 1000, 1) . "kg" : round($val) . $unit;
        };

        if ($maxDaily > 0) {
            if ($stock < $maxDaily) {
                $critical_items[] = "🔴 *" . $ing['name'] . "*: " . $formatVal($stock, $ing['unit']) . " (Max: " . $formatVal($maxDaily, $ing['unit']) . ")";
            }
            elseif ($stock < ($maxDaily * 3)) {
                $low_items[] = "🟡 *" . $ing['name'] . "*: " . $formatVal($stock, $ing['unit']);
            }
        }
        elseif ($stock <= 0) {
            $critical_items[] = "🔴 *" . $ing['name'] . "*: SIN STOCK";
        }
    }

    $msg = "📊 *REPORTE DE INVENTARIO Y VENTAS*\n";
    $msg .= "------------------------------------------\n";
    $msg .= "💰 *Ventas hoy:* $" . number_format($salesStats['total'] ?: 0, 0, ',', '.') . " (" . ($salesStats['count'] ?: 0) . " pedidos)\n\n";

    if (!empty($critical_items)) {
        $msg .= "🔥 *CRÍTICO (Stock < 1 día):*\n" . implode("\n", $critical_items) . "\n\n";
    }

    if (!empty($low_items)) {
        $msg .= "⚠️ *BAJO (Stock < 3 días):*\n" . implode("\n", $low_items) . "\n\n";
    }

    if (empty($critical_items) && empty($low_items)) {
        $msg .= "✅ *Todo el stock se encuentra en niveles saludables.*\n";
    }

    $msg .= "📅 " . date('d-m-Y H:i');

    // 6. Enviar a Telegram
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $msg,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo json_encode(['success' => true, 'message' => 'Reporte enviado a Telegram']);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Error API Telegram', 'api_response' => json_decode($response)]);
    }

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}