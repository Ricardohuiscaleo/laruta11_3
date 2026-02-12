<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $result = [
        'success' => true,
        'data' => []
    ];

    // Verificar visitas hoy
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM site_visits WHERE DATE(created_at) = CURDATE()");
    $result['data']['visits_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Verificar interacciones hoy
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_interactions WHERE DATE(timestamp) = CURDATE()");
    $result['data']['interactions_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Verificar journey hoy
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_journey WHERE DATE(timestamp) = CURDATE()");
    $result['data']['journey_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Verificar product analytics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM product_analytics WHERE views_count > 0");
    $result['data']['products_with_views'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Últimas 5 interacciones
    $stmt = $pdo->query("SELECT action_type, element_type, product_id, timestamp FROM user_interactions ORDER BY timestamp DESC LIMIT 5");
    $result['data']['recent_interactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimas 5 visitas
    $stmt = $pdo->query("SELECT ip_address, city, country, created_at FROM site_visits ORDER BY created_at DESC LIMIT 5");
    $result['data']['recent_visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>