<?php
$config_paths = [
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

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $result = [
        'success' => true,
        'data' => []
    ];

    // Top productos más vistos
    $stmt = $pdo->query("
        SELECT product_name, views_count, clicks_count, cart_adds 
        FROM product_analytics 
        ORDER BY views_count DESC 
        LIMIT 10
    ");
    $result['data']['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Interacciones por tipo hoy
    $stmt = $pdo->query("
        SELECT action_type, COUNT(*) as count 
        FROM user_interactions 
        WHERE DATE(timestamp) = CURDATE() 
        GROUP BY action_type
    ");
    $result['data']['interactions_today'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tiempo promedio en página
    $stmt = $pdo->query("
        SELECT AVG(time_spent) as avg_time, AVG(scroll_depth) as avg_scroll 
        FROM user_journey 
        WHERE DATE(timestamp) = CURDATE() AND time_spent > 0
    ");
    $result['data']['engagement'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Clicks por elemento
    $stmt = $pdo->query("
        SELECT element_type, COUNT(*) as clicks 
        FROM user_interactions 
        WHERE action_type = 'click' AND DATE(timestamp) = CURDATE()
        GROUP BY element_type 
        ORDER BY clicks DESC
    ");
    $result['data']['clicks_by_element'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ubicaciones de visitantes
    $stmt = $pdo->query("
        SELECT city, country, COUNT(*) as visits 
        FROM site_visits 
        WHERE DATE(created_at) = CURDATE() AND city IS NOT NULL
        GROUP BY city, country 
        ORDER BY visits DESC 
        LIMIT 10
    ");
    $result['data']['visitor_locations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>