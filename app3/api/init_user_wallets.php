<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
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
    
    // Obtener todos los usuarios
    $stmt = $pdo->query("SELECT id FROM usuarios");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $created = 0;
    foreach ($users as $user_id) {
        // Crear wallet si no existe
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_wallet (user_id, balance, total_earned, total_used)
            VALUES (?, 0.00, 0.00, 0.00)
        ");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            $created++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_users' => count($users),
        'wallets_created' => $created
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
