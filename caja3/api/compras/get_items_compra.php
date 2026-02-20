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

    // Obtener ingredientes
    $stmt_ing = $pdo->query("
        SELECT 
            i.id,
            i.name,
            i.category,
            i.unit,
            i.current_stock,
            i.min_stock_level,
            'ingredient' as type,
            last_c.ultima_compra_cantidad,
            last_c.stock_despues_compra,
            last_c.fecha_ultima_compra,
            COALESCE((
                SELECT SUM(ABS(it.quantity))
                FROM inventory_transactions it
                WHERE it.ingredient_id = i.id
                AND it.transaction_type = 'sale'
                AND last_c.fecha_ultima_compra IS NOT NULL
                AND it.created_at >= last_c.fecha_ultima_compra
            ), 0) as vendido_desde_compra
        FROM ingredients i
        LEFT JOIN (
            SELECT 
                cd.ingrediente_id,
                cd.cantidad as ultima_compra_cantidad,
                cd.stock_despues as stock_despues_compra,
                c.fecha_compra as fecha_ultima_compra
            FROM compras_detalle cd
            JOIN compras c ON cd.compra_id = c.id
            WHERE cd.ingrediente_id IS NOT NULL
            AND (cd.ingrediente_id, c.fecha_compra) IN (
                SELECT cd2.ingrediente_id, MAX(c2.fecha_compra)
                FROM compras_detalle cd2
                JOIN compras c2 ON cd2.compra_id = c2.id
                WHERE cd2.ingrediente_id IS NOT NULL
                GROUP BY cd2.ingrediente_id
            )
        ) last_c ON last_c.ingrediente_id = i.id
        WHERE i.is_active = 1
        ORDER BY i.name ASC
    ");
    $ingredientes = $stmt_ing->fetchAll(PDO::FETCH_ASSOC);

    // Obtener productos (bebidas, etc)
    $stmt_prod = $pdo->query("
        SELECT 
            p.id,
            p.name,
            c.name as category,
            'unidad' as unit,
            p.stock_quantity as current_stock,
            p.min_stock_level,
            'product' as type,
            p.category_id,
            p.subcategory_id,
            last_c.ultima_compra_cantidad,
            last_c.stock_despues_compra,
            last_c.fecha_ultima_compra,
            COALESCE((
                SELECT SUM(ABS(it.quantity))
                FROM inventory_transactions it
                WHERE it.product_id = p.id
                AND it.transaction_type = 'sale'
                AND last_c.fecha_ultima_compra IS NOT NULL
                AND it.created_at >= last_c.fecha_ultima_compra
            ), 0) as vendido_desde_compra
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN (
            SELECT 
                cd.product_id,
                cd.cantidad as ultima_compra_cantidad,
                cd.stock_despues as stock_despues_compra,
                co.fecha_compra as fecha_ultima_compra
            FROM compras_detalle cd
            JOIN compras co ON cd.compra_id = co.id
            WHERE cd.product_id IS NOT NULL
            AND (cd.product_id, co.fecha_compra) IN (
                SELECT cd2.product_id, MAX(co2.fecha_compra)
                FROM compras_detalle cd2
                JOIN compras co2 ON cd2.compra_id = co2.id
                WHERE cd2.product_id IS NOT NULL
                GROUP BY cd2.product_id
            )
        ) last_c ON last_c.product_id = p.id
        WHERE p.is_active = 1
        ORDER BY p.name ASC
    ");
    $productos = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

    // Combinar ambos arrays
    $items = array_merge($ingredientes, $productos);

    echo json_encode($items);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
