<?php
header('Content-Type: application/json');

$config_paths = [__DIR__ . '/../config.php', __DIR__ . '/../../config.php'];
$config = null;
foreach ($config_paths as $p) { if (file_exists($p)) { $config = require $p; break; } }
if (!$config) { echo json_encode(['error' => 'Config no encontrado']); exit; }

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $ingredientes = $pdo->query("SELECT id, name, category, unit, current_stock, 'ingredient' as type FROM ingredients WHERE is_active = 1 ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
    $productos = $pdo->query("SELECT p.id, p.name, c.name as category, 'unidad' as unit, p.stock_quantity as current_stock, 'product' as type FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 AND p.subcategory_id IN (10,11,27,28) ORDER BY p.name")->fetchAll(PDO::FETCH_ASSOC);

    $date = date('Y-m-d');
    $md = "# Ajuste Inventario $date\n\n";

    // Ingredientes agrupados por categoría
    $groups = [];
    foreach ($ingredientes as $r) {
        $cat = $r['category'] ?: 'Ingredientes';
        $groups[$cat][] = $r;
    }
    foreach ($groups as $cat => $items) {
        $md .= "## $cat\n";
        foreach ($items as $item) {
            $stock = rtrim(rtrim(number_format((float)$item['current_stock'], 3, '.', ''), '0'), '.');
            $md .= "- {$item['name']}: $stock {$item['unit']}\n";
        }
        $md .= "\n";
    }

    // Bebidas
    if (!empty($productos)) {
        $md .= "## Bebidas\n";
        foreach ($productos as $item) {
            $md .= "- {$item['name']}: {$item['current_stock']} unidad\n";
        }
    }

    echo json_encode(['success' => true, 'markdown' => trim($md)]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
