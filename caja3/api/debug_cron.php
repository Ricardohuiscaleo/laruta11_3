<?php
header('Content-Type: text/plain');

echo "=== DEBUG CRON ===\n";
echo "Fecha/Hora: " . date('Y-m-d H:i:s') . "\n";

// Buscar config
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if (!$configPath) {
    die("ERROR: Config no encontrado\n");
}
echo "Config: $configPath\n";

$config = include $configPath;

try {
    $conn = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "DB: Conectado\n";
} catch (PDOException $e) {
    die("ERROR DB: " . $e->getMessage() . "\n");
}

// Verificar templates
$stmt = $conn->prepare("SELECT type, COUNT(*) as count FROM checklist_templates WHERE active = 1 GROUP BY type");
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Templates:\n";
foreach ($templates as $t) {
    echo "- {$t['type']}: {$t['count']} items\n";
}

// Verificar checklists existentes hoy
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT type, COUNT(*) as count FROM checklists WHERE scheduled_date = ? GROUP BY type");
$stmt->execute([$today]);
$existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Checklists hoy ($today):\n";
foreach ($existing as $e) {
    echo "- {$e['type']}: {$e['count']}\n";
}

echo "=== FIN DEBUG ===\n";
?>