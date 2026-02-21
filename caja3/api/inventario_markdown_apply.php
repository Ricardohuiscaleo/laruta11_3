<?php
header('Content-Type: application/json');

$config_paths = [__DIR__ . '/../config.php', __DIR__ . '/../../config.php'];
$config = null;
foreach ($config_paths as $p) { if (file_exists($p)) { $config = require $p; break; } }
if (!$config) { echo json_encode(['error' => 'Config no encontrado']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$markdown = $input['markdown'] ?? '';
$apply = $input['apply'] ?? false;

if (!$markdown) { echo json_encode(['error' => 'Markdown vacío']); exit; }

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Cargar todos los ingredientes activos
    $dbItems = $pdo->query("SELECT id, name, current_stock, unit FROM ingredients WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    $byName = [];
    foreach ($dbItems as $item) {
        $byName[strtolower(trim($item['name']))] = $item;
    }

    // Parsear markdown: líneas "- Nombre: cantidad unidad"
    $changes = [];
    $notFound = [];

    foreach (explode("\n", $markdown) as $line) {
        $line = trim($line);
        if (!str_starts_with($line, '- ')) continue;
        $line = substr($line, 2);

        if (!preg_match('/^(.+?):\s*([\d.]+)\s*(\S+)?$/', $line, $m)) continue;

        $name = trim($m[1]);
        $newStock = (float)$m[2];
        $unit = trim($m[3] ?? '');
        $key = strtolower($name);

        // Buscar exacto primero, luego fuzzy
        $match = $byName[$key] ?? null;
        if (!$match) {
            // Fuzzy: buscar si el nombre de BD contiene el texto o viceversa
            foreach ($byName as $k => $item) {
                if (str_contains($k, $key) || str_contains($key, $k)) {
                    $match = $item;
                    break;
                }
            }
        }

        if ($match) {
            $changes[] = [
                'id'        => $match['id'],
                'name'      => $match['name'],
                'old_stock' => (float)$match['current_stock'],
                'new_stock' => $newStock,
                'unit'      => $match['unit'],
                'changed'   => (float)$match['current_stock'] !== $newStock,
            ];
        } else {
            $notFound[] = $name;
        }
    }

    if ($apply) {
        $stmt = $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?");
        $updated = 0;
        foreach ($changes as $c) {
            if ($c['changed']) {
                $stmt->execute([$c['new_stock'], $c['id']]);
                $updated++;
            }
        }
        echo json_encode(['success' => true, 'updated' => $updated]);
    } else {
        echo json_encode([
            'success'   => true,
            'changes'   => $changes,
            'not_found' => $notFound,
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
