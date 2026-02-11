<?php
$config = require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$apis = [
    // Admin APIs
    'admin_dashboard.php' => '📊 Dashboard Admin',
    'admin_auth.php' => '🔐 Autenticación Admin',
    'products.php' => '📦 Gestión de Productos',
    'categories.php' => '🏷️ Categorías',
    
    // TUU Payment APIs
    'tuu_test_real.php' => '🧪 Test TUU Real',
    'tuu_payment_gateway.php' => '💳 Gateway TUU',
    'tuu_device_config.php' => '⚙️ Config Dispositivos TUU',
    'tuu_create_payment.php' => '🔄 Crear Pago TUU',
    'tuu_payment_query.php' => '🔍 Consultar Estado TUU',
    'tuu_clear_queue.php' => '🧹 Limpiar Cola TUU',
    'tuu_webhook_listener.php' => '📡 Webhook TUU',
    'tuu_status_check.php' => '✅ Estado Pagos TUU',
    'tuu_payment_refund.php' => '💸 Reembolsos TUU',
    
    // Business Logic APIs
    'get_productos.php' => '🍔 Obtener Productos',
    'get_ingredientes.php' => '🥬 Obtener Ingredientes',
    'get_recetas.php' => '📝 Obtener Recetas',
    'get_proyeccion.php' => '📈 Proyección Financiera',
    'registrar_venta.php' => '💰 Registrar Venta',
    
    // User & Auth APIs
    'auth/check_session.php' => '👤 Verificar Sesión',
    'auth/login.php' => '🔑 Login Usuario',
    'users/get_profile.php' => '👤 Perfil Usuario',
    
    // Location & Delivery APIs
    'location/geocode.php' => '📍 Geocodificación',
    'location/check_delivery_zone.php' => '🚚 Zona de Delivery',
    'food_trucks/get_nearby.php' => '🚛 Food Trucks Cercanos',
    
    // Notifications
    'notifications/get_notifications.php' => '🔔 Notificaciones',
    
    // Jobs & Tracker APIs
    'jobs/start_application.php' => '💼 Iniciar Postulación',
    'tracker/get_candidates.php' => '📋 Obtener Candidatos',
    'tracker/get_dashboard_stats.php' => '📊 Stats Tracker'
];

$results = [];

foreach ($apis as $file => $name) {
    $url = "http://{$_SERVER['HTTP_HOST']}/api/{$file}";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]);
    
    $start = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $time = round((microtime(true) - $start) * 1000);
    
    $status = 'error';
    $message = 'No responde';
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $status = 'ok';
            $message = 'Funcionando';
        } else {
            $status = 'warning';
            $message = 'Responde pero no JSON válido';
        }
    }
    
    $results[] = [
        'name' => $name,
        'file' => $file,
        'status' => $status,
        'message' => $message,
        'response_time' => $time
    ];
}

// Test multiple DB connections
$databases = [
    'app' => ['host' => $config['app_db_host'], 'name' => $config['app_db_name'], 'user' => $config['app_db_user'], 'pass' => $config['app_db_pass']],
    'usuarios' => ['host' => $config['ruta11_db_host'], 'name' => $config['ruta11_db_name'], 'user' => $config['ruta11_db_user'], 'pass' => $config['ruta11_db_pass']],
    'calcularuta11' => ['host' => $config['Calcularuta11_db_host'], 'name' => $config['Calcularuta11_db_name'], 'user' => $config['Calcularuta11_db_user'], 'pass' => $config['Calcularuta11_db_pass']]
];

$db_results = [];
foreach ($databases as $key => $db) {
    try {
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']}",
            $db['user'],
            $db['pass']
        );
        $db_results[$key] = ['status' => 'ok', 'message' => 'Conectada', 'name' => $db['name']];
    } catch (Exception $e) {
        $db_results[$key] = ['status' => 'error', 'message' => 'Error: ' . $e->getMessage(), 'name' => $db['name']];
    }
}

echo json_encode([
    'success' => true,
    'apis' => $results,
    'databases' => $db_results,
    'timestamp' => date('Y-m-d H:i:s')
]);