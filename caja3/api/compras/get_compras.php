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

    $limit = max(1, min(200, intval($_GET['limit'] ?? 50)));
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $total = $pdo->query("SELECT COUNT(*) FROM compras")->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM compras ORDER BY fecha_compra DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener items de cada compra
    $stmtItems = $pdo->prepare("SELECT * FROM compras_detalle WHERE compra_id = ?");
    for ($i = 0; $i < count($compras); $i++) {
        $stmtItems->execute([$compras[$i]['id']]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        $compras[$i]['items'] = $items;
        $compras[$i]['items_count'] = count($items);
    }

    echo json_encode([
        'success' => true,
        'compras' => $compras,
        'total_compras' => intval($total),
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
