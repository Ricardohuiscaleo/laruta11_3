<?php
date_default_timezone_set('America/Santiago');

echo "Iniciando cronjob - " . date('Y-m-d H:i:s') . "\n";

$logFile = __DIR__ . '/cron_executions.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Cronjob ejecutado\n", FILE_APPEND);
echo "Log guardado\n";

function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../', '../../../../../../'];
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
    echo "ERROR: Config no encontrado\n";
    exit(1);
}
echo "Config encontrado: $configPath\n";

$config = include $configPath;

try {
    $conn = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "BD conectada\n";
} catch (PDOException $e) {
    echo "ERROR BD: " . $e->getMessage() . "\n";
    exit(1);
}

$date = date('Y-m-d');
echo "Fecha actual: $date\n";
$created = 0;

foreach (['apertura', 'cierre'] as $type) {
    $checklist_date = $date;
    if ($type === 'cierre') {
        $checklist_date = date('Y-m-d', strtotime($date . ' +1 day'));
    }
    
    $stmt = $conn->prepare("SELECT id FROM checklists WHERE type = ? AND scheduled_date = ? AND total_items > 0");
    $stmt->execute([$type, $checklist_date]);
    
    if ($stmt->fetch()) {
        echo "SKIP: $type ya existe para $checklist_date\n";
        continue;
    }
    
    echo "Creando $type para $checklist_date...\n";
    
    $scheduled_time = $type === 'apertura' ? '18:00:00' : '00:45:00';
    
    $templates = $conn->prepare("SELECT * FROM checklist_templates WHERE type = ? AND active = 1 ORDER BY item_order");
    $templates->execute([$type]);
    $items = $templates->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($items) === 0) {
        echo "ERROR: No hay templates para $type\n";
        continue;
    }
    echo "Templates: " . count($items) . " items\n";
    
    $stmt = $conn->prepare("
        INSERT INTO checklists (type, scheduled_time, scheduled_date, total_items, completed_items, status)
        VALUES (?, ?, ?, ?, 0, 'pending')
    ");
    $stmt->execute([$type, $scheduled_time, $checklist_date, count($items)]);
    $checklist_id = $conn->lastInsertId();
    
    foreach ($items as $item) {
        $stmt = $conn->prepare("
            INSERT INTO checklist_items (checklist_id, item_order, description, requires_photo)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$checklist_id, $item['item_order'], $item['description'], $item['requires_photo']]);
    }
    
    $created++;
    echo "✓ $type creado\n";
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM checklists WHERE scheduled_date = ?");
$stmt->execute([$date]);
$total = $stmt->fetchColumn();

echo "\nRESULTADO FINAL:\n";
if ($created > 0) {
    echo "Se crearon $created checklists nuevos. Total hoy: $total checklists.\n";
} else {
    echo "Ya fueron creados $total checklists HOY.\n";
}
echo "Finalizado: " . date('Y-m-d H:i:s') . "\n";
?>
