<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Bebidas: category_id=5, excluir té(28), café(27), jugos(10), aguas(154,155 por nombre)
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.stock_quantity, p.min_stock_level, p.price, s.name as subcategory
        FROM products p
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        WHERE p.category_id = 5
          AND p.subcategory_id NOT IN (10, 27, 28)
          AND p.is_active = 1
          AND p.name NOT LIKE '%Agua%'
          AND p.name NOT LIKE '%Jugo%'
          AND p.name NOT LIKE '%Nectar%'
          AND p.name NOT LIKE '%NECTAR%'
        ORDER BY s.name ASC, p.name ASC
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $TARGET = 12;
    $fecha = date('d/m/Y');

    // Agrupar por subcategoría
    $grupos = [];
    foreach ($productos as $p) {
        $sub = $p['subcategory'] ?: 'Otras Bebidas';
        $grupos[$sub][] = $p;
    }

    $criticos = [];
    $comprar = [];

    // Formato WhatsApp: negrita con *, sin tablas
    $md = "📦 *REPORTE BEBIDAS — $fecha*\n";
    $md .= "_(objetivo: $TARGET unidades por producto)_\n\n";

    foreach ($grupos as $sub => $items) {
        $md .= "*$sub*\n";
        foreach ($items as $p) {
            $stock = (int)$p['stock_quantity'];
            $sugerido = max(0, $TARGET - $stock);
            $emoji = $stock <= 2 ? '🔴' : ($stock <= 5 ? '🟡' : '🟢');
            if ($sugerido > 0) {
                $md .= "$emoji {$p['name']} — stock: $stock → comprar: *$sugerido*\n";
            } else {
                $md .= "$emoji {$p['name']} — stock: $stock ✓\n";
            }
            if ($stock <= 2) $criticos[] = $p['name'];
            if ($sugerido > 0) $comprar[] = ['nombre' => $p['name'], 'cantidad' => $sugerido, 'precio' => (float)$p['price']];
        }
        $md .= "\n";
    }

    // Resumen de compra
    if (!empty($comprar)) {
        $total = 0;
        $md .= "─────────────────\n";
        $md .= "🛒 *COMPRA SUGERIDA*\n";
        foreach ($comprar as $c) {
            $sub = $c['cantidad'] * $c['precio'];
            $total += $sub;
            $md .= "• {$c['nombre']}: *{$c['cantidad']} u* — \$" . number_format($sub, 0, ',', '.') . "\n";
        }
        $md .= "\n*Total estimado: \$" . number_format($total, 0, ',', '.') . "*\n";
    }

    if (!empty($criticos)) {
        $md .= "\n⚠️ *CRÍTICOS:* " . implode(', ', $criticos) . "\n";
    }

    echo json_encode(['success' => true, 'markdown' => $md]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
