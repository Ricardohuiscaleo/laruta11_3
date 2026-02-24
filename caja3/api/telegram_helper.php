<?php
/**
 * Shared logic to generate the inventory report message.
 * Returns the formatted report string.
 */

/**
 * Detecta el rango del turno actual basado en la hora de Chile (UTC-3).
 * El turno va desde las 17:30 de un día hasta las 04:00 del día siguiente.
 */
function getCurrentShiftRange()
{
    // Establecer zona horaria de Chile para los cálculos
    $tz = new DateTimeZone('America/Santiago');
    $now = new DateTime('now', $tz);
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');

    // Si es entre las 00:00 y las 04:00, estamos en la segunda parte del turno que empezó ayer
    if ($currentHour < 4) {
        $startDate = (clone $now)->modify('-1 day')->format('Y-m-d 17:30:00');
        $endDate = $now->format('Y-m-d 04:00:00');
    }
    // Si es después de las 17:30, estamos en la primera parte del turno de hoy
    else if ($currentHour > 17 || ($currentHour == 17 && $currentMinute >= 30)) {
        $startDate = $now->format('Y-m-d 17:30:00');
        $endDate = (clone $now)->modify('+1 day')->format('Y-m-d 04:00:00');
    }
    // En el "tiempo muerto" (04:00 a 17:30), mostramos el último turno completado
    else {
        $startDate = (clone $now)->modify('-1 day')->format('Y-m-d 17:30:00');
        $endDate = $now->format('Y-m-d 04:00:00');
    }

    // Convertir a UTC para la base de datos (Chile UTC-3 -> UTC: sumar 3 horas)
    $startUTC = date('Y-m-d H:i:s', strtotime($startDate . ' + 3 hours'));
    $endUTC = date('Y-m-d H:i:s', strtotime($endDate . ' + 3 hours'));

    return [
        'start' => $startUTC,
        'end' => $endUTC,
        'label' => date('d/m H:i', strtotime($startDate)) . " al " . date('d/m H:i', strtotime($endDate))
    ];
}

function generateInventoryReport($pdo)
{
    // 1. Obtener Rango del Turno
    $shift = getCurrentShiftRange();
    $startDate = $shift['start'];
    $endDate = $shift['end'];

    // 2. Ventas del turno
    $salesSql = "SELECT COUNT(*) as count, SUM(installment_amount) as total 
                 FROM tuu_orders 
                 WHERE created_at >= ? AND created_at < ? 
                 AND payment_status = 'paid'
                 AND order_number NOT LIKE 'RL6-%'";
    $stmt = $pdo->prepare($salesSql);
    $stmt->execute([$startDate, $endDate]);
    $salesStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Consumo de ingredientes del turno
    $ordersSql = "SELECT order_number FROM tuu_orders 
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

    // 5. Generar Bloques de Mensaje
    $all_items = [];
    foreach ($ingredient_consumption as &$ing) {
        $key = $ing['ingredient_id'] ? "ing_" . $ing['ingredient_id'] : "prod_" . $ing['product_id'];
        $maxDaily = $max_data_map[$key] ?? 0;

        $stock = floatval($ing['stock_actual']);
        // Normalización para el cálculo
        if ($ing['unit'] === 'g') {
            if ($stock < 100 && $stock > 0)
                $stock *= 1000;
            if ($maxDaily < 100 && $maxDaily > 0)
                $maxDaily *= 1000;
        }

        $ing['max_daily'] = $maxDaily;
        $ing['stock_norm'] = $stock;
        $ing['criticidad'] = ($maxDaily > 0 && $stock < $maxDaily) || ($maxDaily == 0 && $stock <= 0) ? 2 : (($maxDaily > 0 && $stock < $maxDaily * 3) ? 1 : 0);
        $all_items[] = $ing;
    }

    // Ordenar por criticidad
    usort($all_items, function ($a, $b) {
        if ($a['criticidad'] != $b['criticidad'])
            return $b['criticidad'] - $a['criticidad'];
        return strcmp($a['name'], $b['name']);
    });

    $critical_items = [];
    $low_items = [];
    $healthy_items = [];

    $formatVal = function ($val, $unit) {
        return $unit === 'g' && $val >= 1000 ? number_format($val / 1000, 1) . "kg" : round($val) . $unit;
    };

    foreach ($all_items as $item) {
        $line = " *" . $item['name'] . "*: " . $formatVal($item['stock_norm'], $item['unit']);
        if ($item['criticidad'] == 2) {
            $critical_items[] = "🔴" . $line . " (Max: " . $formatVal($item['max_daily'], $item['unit']) . ")";
        }
        elseif ($item['criticidad'] == 1) {
            $low_items[] = "🟡" . $line;
        }
        else {
            $healthy_items[] = "🟢" . $line;
        }
    }

    $msg = "📊 *REPORTE DE INVENTARIO Y VENTAS*\n";
    $msg .= "🕒 *Turno:* " . $shift['label'] . "\n";
    $msg .= "------------------------------------------\n";
    $msg .= "💰 *Ventas del turno:* $" . number_format($salesStats['total'] ?: 0, 0, ',', '.') . " (" . ($salesStats['count'] ?: 0) . " pedidos)\n\n";

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

    return $msg;
}

/**
 * Genera un reporte resumido del inventario actual de todos los ingredientes y productos con stock.
 */
/**
 * Genera un reporte resumido del inventario actual de todos los ingredientes y productos con stock,
 * agrupados por categorías para mejor lectura.
 */
function generateGeneralInventoryReport($pdo)
{
    // 1. Obtener todos los ingredientes activos con su categoría
    $sqlIng = "SELECT id, name, current_stock as stock, unit, category FROM ingredients WHERE is_active = 1 ORDER BY category ASC, name ASC";
    $ingredients = $pdo->query($sqlIng)->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener productos con stock (habitualmente bebidas) con el nombre de su categoría
    $sqlProd = "SELECT p.id, p.name, p.stock_quantity as stock, 'un' as unit, c.name as category 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1 
                AND (p.category_id = 5 OR p.stock_quantity > 0)
                ORDER BY c.name ASC, p.name ASC";
    $products = $pdo->query($sqlProd)->fetchAll(PDO::FETCH_ASSOC);

    // Unificar en una lista plana primero
    $all = [];
    foreach ($ingredients as $i) {
        $all[] = [
            'name' => $i['name'],
            'stock' => $i['stock'],
            'unit' => $i['unit'],
            'category' => $i['category'] ?: 'Otros',
            'id' => $i['id'],
            'type' => 'ing'
        ];
    }
    foreach ($products as $p) {
        $all[] = [
            'name' => $p['name'],
            'stock' => $p['stock'],
            'unit' => $p['unit'],
            'category' => $p['category'] ?: 'Productos',
            'id' => $p['id'],
            'type' => 'prod'
        ];
    }

    // 3. Obtener Max Consumo para criticidad (Batch)
    $ing_ids = array_column($ingredients, 'id');
    $prod_ids = array_column($products, 'id');
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

    // 4. Calcular criticidad y agrupar por categoría
    $grouped = [];
    $categoryIcons = [
        'Carnes' => '🥩', 'Aves' => '🍗', 'Panes' => '🍞', 'Vegetales' => '🥬',
        'Salsas' => '🥫', 'Lácteos' => '🧀', 'Embutidos' => '🌭', 'Bebidas' => '🥤',
        'Congelados' => '❄️', 'Pescados' => '🐟', 'Otros' => '📦'
    ];

    foreach ($all as &$item) {
        $key = $item['type'] == 'ing' ? "ing_" . $item['id'] : "prod_" . $item['id'];
        $maxVal = $max_data_map[$key] ?? 0;
        $stock = floatval($item['stock']);
        $unit = $item['unit'];

        if ($unit === 'g') {
            if ($stock < 100 && $stock > 0)
                $stock *= 1000;
            if ($maxVal < 100 && $maxVal > 0)
                $maxVal *= 1000;
        }

        $item['max_daily'] = $maxVal;
        $item['stock_norm'] = $stock;
        $item['criticidad'] = ($maxVal > 0 && $stock < $maxVal) || ($maxVal == 0 && $stock <= 0) ? 2 : (($maxVal > 0 && $stock < $maxVal * 3) ? 1 : 0);

        $catName = $item['category'];
        if (!isset($grouped[$catName]))
            $grouped[$catName] = [];
        $grouped[$catName][] = $item;
    }

    // 5. Formatear Mensaje
    $msg = "📋 *INVENTARIO GENERAL POR CATEGORÍA*\n";
    $msg .= "------------------------------------------\n";

    $formatVal = function ($val, $unit) {
        return $unit === 'g' && $val >= 1000 ? number_format($val / 1000, 1) . "kg" : round($val) . $unit;
    };

    // Ordenar categorías (Bebidas y Carnes primero por ejemplo, o alfabético)
    ksort($grouped);

    foreach ($grouped as $catName => $items) {
        // Ordenar items por criticidad dentro de la categoría
        usort($items, function ($a, $b) {
            if ($a['criticidad'] != $b['criticidad'])
                return $b['criticidad'] - $a['criticidad'];
            return strcmp($a['name'], $b['name']);
        });

        $icon = $categoryIcons[$catName] ?? ($categoryIcons['Otros']);
        $msg .= "\n" . $icon . " *" . strtoupper($catName) . "*\n";

        foreach ($items as $item) {
            $emoji = $item['criticidad'] == 2 ? "🔴" : ($item['criticidad'] == 1 ? "🟡" : "🟢");
            $msg .= "  {$emoji} *{$item['name']}*: " . $formatVal($item['stock_norm'], $item['unit']);
            if ($item['criticidad'] == 2)
                $msg .= " (Max: " . $formatVal($item['max_daily'], $item['unit']) . ")";
            $msg .= "\n";
        }
    }

    $msg .= "\n📅 " . date('d-m-Y H:i');
    return $msg;
}

function sendTelegramMessage($token, $chatId, $message, $buttons = null)
{
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    if ($buttons) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $httpCode === 200,
        'code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}