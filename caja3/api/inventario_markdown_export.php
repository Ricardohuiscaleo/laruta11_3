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

    $rows = $pdo->query("SELECT id, name, current_stock, unit, category FROM ingredients WHERE is_active = 1 ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por categoría
    $groups = [];
    foreach ($rows as $r) {
        $cat = $r['category'] ?: 'Ingredientes';
        $groups[$cat][] = $r;
    }

    // Generar markdown
    $date = date('Y-m-d');
    $md = "# Ajuste Inventario $date\n\n";
    foreach ($groups as $cat => $items) {
        $md .= "## $cat\n";
        foreach ($items as $item) {
            $stock = rtrim(rtrim(number_format((float)$item['current_stock'], 3, '.', ''), '0'), '.');
            $md .= "- {$item['name']}: $stock {$item['unit']}\n";
        }
        $md .= "\n";
    }

    echo json_encode(['success' => true, 'markdown' => trim($md), 'items' => $rows]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
