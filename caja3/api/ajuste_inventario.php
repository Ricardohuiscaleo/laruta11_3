<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'preview'; // 'preview' o 'apply'
$markdown = $input['markdown'] ?? '';

// Cargar todos los ingredientes activos
$stmt = $pdo->query("SELECT id, name, current_stock, unit FROM ingredients WHERE is_active = 1 ORDER BY name");
$allIngredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($action === 'list') {
    // Devolver inventario actual como markdown listo para copiar
    $lines = ["# Ajuste Inventario " . date('Y-m-d'), "", "## Ingredientes"];
    foreach ($allIngredients as $ing) {
        $lines[] = "- {$ing['name']}: {$ing['current_stock']} {$ing['unit']}";
    }
    echo json_encode(['success' => true, 'markdown' => implode("\n", $lines)]);
    exit;
}

// Parsear markdown
function parseMarkdown($text) {
    $items = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if (!preg_match('/^-\s+(.+?):\s*([\d.]+)\s*(\w+)?$/', $line, $m)) continue;
        $items[] = [
            'name'  => trim($m[1]),
            'qty'   => (float) $m[2],
            'unit'  => trim($m[3] ?? '')
        ];
    }
    return $items;
}

// Match fuzzy por nombre (similar_text)
function findBestMatch($name, $ingredients) {
    $best = null;
    $bestScore = 0;
    foreach ($ingredients as $ing) {
        similar_text(strtolower($name), strtolower($ing['name']), $pct);
        if ($pct > $bestScore) {
            $bestScore = $pct;
            $best = $ing;
        }
    }
    return $bestScore >= 60 ? ['ingredient' => $best, 'score' => round($bestScore)] : null;
}

$parsed = parseMarkdown($markdown);
$results = [];

foreach ($parsed as $item) {
    $match = findBestMatch($item['name'], $allIngredients);
    if ($match) {
        $ing = $match['ingredient'];
        $results[] = [
            'status'       => 'found',
            'input_name'   => $item['name'],
            'matched_name' => $ing['name'],
            'matched_id'   => $ing['id'],
            'score'        => $match['score'],
            'old_stock'    => (float) $ing['current_stock'],
            'new_stock'    => $item['qty'],
            'unit'         => $item['unit'] ?: $ing['unit'],
            'db_unit'      => $ing['unit'],
        ];
    } else {
        $results[] = [
            'status'     => 'not_found',
            'input_name' => $item['name'],
            'qty'        => $item['qty'],
        ];
    }
}

if ($action === 'apply') {
    $applied = 0;
    foreach ($results as $r) {
        if ($r['status'] !== 'found') continue;
        $stmt = $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$r['new_stock'], $r['matched_id']]);
        $applied++;
    }
    echo json_encode(['success' => true, 'applied' => $applied, 'results' => $results]);
} else {
    echo json_encode(['success' => true, 'results' => $results]);
}
