<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
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
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $result = [
        'success' => true,
        'data' => []
    ];

    // === MÉTRICAS PRINCIPALES ===
    
    // Visitas (con fallback si no existe la tabla)
    try {
        $visits_today = $pdo->query("SELECT COUNT(*) FROM site_visits WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $visits_week = $pdo->query("SELECT COUNT(*) FROM site_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $visits_month = $pdo->query("SELECT COUNT(*) FROM site_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (Exception $e) {
        $visits_today = 0;
        $visits_week = 0;
        $visits_month = 0;
    }
    
    // Usuarios
    try {
        $total_users = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        $new_users_today = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    } catch (Exception $e) {
        $total_users = 0;
        $new_users_today = 0;
    }
    
    // Productos
    try {
        $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
        $products_viewed_today = $pdo->query("SELECT COUNT(DISTINCT product_id) FROM product_analytics WHERE DATE(last_interaction) = CURDATE()")->fetchColumn();
    } catch (Exception $e) {
        $total_products = 0;
        $products_viewed_today = 0;
    }
    
    // Interacciones
    try {
        $interactions_today = $pdo->query("SELECT COUNT(*) FROM user_interactions WHERE DATE(timestamp) = CURDATE()")->fetchColumn();
        $cart_adds_today = $pdo->query("SELECT COUNT(*) FROM user_interactions WHERE action_type = 'add_to_cart' AND DATE(timestamp) = CURDATE()")->fetchColumn();
    } catch (Exception $e) {
        $interactions_today = 0;
        $cart_adds_today = 0;
    }

    $result['data']['metrics'] = [
        'visits' => [
            'today' => $visits_today,
            'week' => $visits_week,
            'month' => $visits_month
        ],
        'users' => [
            'total' => $total_users,
            'new_today' => $new_users_today
        ],
        'products' => [
            'total' => $total_products,
            'viewed_today' => $products_viewed_today
        ],
        'interactions' => [
            'today' => $interactions_today,
            'cart_adds_today' => $cart_adds_today
        ]
    ];

    // === GRÁFICOS ===
    
    // 1. Visitas últimos 7 días (con fallback para datos existentes)
    try {
        $visits_7days = $pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM site_visits 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Si no hay datos recientes, usar los últimos 7 días de datos disponibles
        if (empty($visits_7days)) {
            $visits_7days = $pdo->query("
                SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM site_visits 
                GROUP BY DATE(created_at) 
                ORDER BY date DESC 
                LIMIT 7
            ")->fetchAll(PDO::FETCH_ASSOC);
            $visits_7days = array_reverse($visits_7days);
        }
    } catch (Exception $e) {
        $visits_7days = [];
    }
    
    $result['data']['charts']['visits_7days'] = $visits_7days;

    // 2. Interacciones por tipo (hoy)
    try {
        $interactions_by_type = $pdo->query("
            SELECT action_type, COUNT(*) as count 
            FROM user_interactions 
            WHERE DATE(timestamp) = CURDATE() 
            GROUP BY action_type
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $interactions_by_type = [];
    }
    $result['data']['charts']['interactions_by_type'] = $interactions_by_type;

    // 3. Productos más vistos
    try {
        $top_products = $pdo->query("
            SELECT p.name, pa.views_count, pa.clicks_count 
            FROM product_analytics pa 
            JOIN products p ON pa.product_id = p.id 
            WHERE pa.views_count > 0 
            ORDER BY pa.views_count DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $top_products = [];
    }
    $result['data']['charts']['top_products'] = $top_products;

    // 4. Actividad por horas (hoy)
    try {
        $hourly_activity = $pdo->query("
            SELECT HOUR(timestamp) as hour, COUNT(*) as count 
            FROM user_interactions 
            WHERE DATE(timestamp) = CURDATE() 
            GROUP BY HOUR(timestamp) 
            ORDER BY hour ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $hourly_activity = [];
    }
    $result['data']['charts']['hourly_activity'] = $hourly_activity;

    // 5. Países/ciudades de visitantes
    try {
        $visitor_locations = $pdo->query("
            SELECT country, city, COUNT(*) as count 
            FROM site_visits 
            WHERE country IS NOT NULL AND country != '' 
            GROUP BY country, city 
            ORDER BY count DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $visitor_locations = [];
    }
    $result['data']['charts']['visitor_locations'] = $visitor_locations;

    // 6. Conversión de carrito
    try {
        $cart_conversion = $pdo->query("
            SELECT 
                SUM(CASE WHEN action_type = 'view' THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN action_type = 'add_to_cart' THEN 1 ELSE 0 END) as cart_adds,
                SUM(CASE WHEN action_type = 'purchase' THEN 1 ELSE 0 END) as purchases
            FROM user_interactions 
            WHERE DATE(timestamp) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $cart_conversion = ['views' => 0, 'cart_adds' => 0, 'purchases' => 0];
    }
    $result['data']['charts']['cart_conversion'] = $cart_conversion;

    // === ESTADÍSTICAS ADICIONALES ===
    
    // Tiempo promedio en página
    $avg_time_on_page = $pdo->query("
        SELECT AVG(time_spent) as avg_time 
        FROM user_journey 
        WHERE time_spent > 0 AND time_spent < 3600
    ")->fetchColumn();
    
    $result['data']['stats']['avg_time_on_page'] = round($avg_time_on_page, 2);

    // Bounce rate (usuarios que solo ven 1 página)
    $single_page_sessions = $pdo->query("
        SELECT COUNT(DISTINCT session_id) 
        FROM user_journey 
        GROUP BY session_id 
        HAVING COUNT(*) = 1
    ")->rowCount();
    
    $total_sessions = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM user_journey")->fetchColumn();
    $bounce_rate = $total_sessions > 0 ? ($single_page_sessions / $total_sessions) * 100 : 0;
    
    $result['data']['stats']['bounce_rate'] = round($bounce_rate, 2);

    // Dispositivos más usados
    try {
        $top_devices = $pdo->query("
            SELECT 
                CASE 
                    WHEN user_agent LIKE '%Mobile%' OR device_type = 'mobile' THEN 'Mobile'
                    WHEN user_agent LIKE '%Tablet%' OR device_type = 'tablet' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device_type,
                COUNT(*) as count
            FROM site_visits 
            WHERE user_agent IS NOT NULL 
            GROUP BY device_type 
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $top_devices = [];
    }
    
    $result['data']['charts']['device_types'] = $top_devices;

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>