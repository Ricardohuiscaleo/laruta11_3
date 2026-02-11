<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

    // Verificar tabla cash_register
    $stmt = $pdo->query("SHOW TABLES LIKE 'cash_register'");
    $cashRegisterExists = $stmt->rowCount() > 0;

    // Obtener datos de cash_register
    $cashRegisterData = null;
    if ($cashRegisterExists) {
        $stmt = $pdo->query("SELECT * FROM cash_register ORDER BY id DESC LIMIT 1");
        $cashRegisterData = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Verificar tabla compras
    $stmt = $pdo->query("SHOW TABLES LIKE 'compras'");
    $comprasExists = $stmt->rowCount() > 0;

    // Obtener compras pendientes
    $comprasPendientes = null;
    if ($comprasExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count, SUM(monto_total) as total FROM compras WHERE estado = 'pendiente'");
        $comprasPendientes = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'debug' => [
            'cash_register_exists' => $cashRegisterExists,
            'cash_register_data' => $cashRegisterData,
            'compras_exists' => $comprasExists,
            'compras_pendientes' => $comprasPendientes,
            'db_name' => $config['app_db_name']
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
