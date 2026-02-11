<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config_path = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config_path = $path;
        break;
    }
}

if (!$config_path) {
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

$config = require_once $config_path;

// Usar credenciales de la app principal
$host = $config['app_db_host'];
$dbname = $config['app_db_name'];
$username = $config['app_db_user'];
$password = $config['app_db_pass'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tablas si no existen
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT DEFAULT 1
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        user_agent TEXT,
        page_url VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS productos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        sku VARCHAR(100),
        price DECIMAL(10,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS ventas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        total_amount DECIMAL(10,2),
        customer_name VARCHAR(255),
        customer_email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        user_agent TEXT,
        city VARCHAR(100),
        country VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_interactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255),
        action_type VARCHAR(50),
        element_type VARCHAR(50),
        product_id INT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_journey (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(255),
        page_url VARCHAR(500),
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        product_name VARCHAR(200),
        views_count INT DEFAULT 0,
        clicks_count INT DEFAULT 0,
        cart_adds INT DEFAULT 0,
        cart_removes INT DEFAULT 0,
        purchase_count INT DEFAULT 0,
        last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $cleaned = [];
    
    // 1. Limpiar usuarios de prueba/falsos
    $fake_users = $pdo->prepare("DELETE FROM usuarios WHERE 
        email LIKE '%test%' OR 
        email LIKE '%fake%' OR 
        email LIKE '%demo%' OR
        name LIKE '%test%' OR
        name LIKE '%fake%' OR
        name LIKE '%demo%' OR
        phone LIKE '%000000%' OR
        phone LIKE '%111111%' OR
        phone LIKE '%123456%'
    ");
    $fake_users->execute();
    $cleaned['fake_users'] = $fake_users->rowCount();
    
    // 2. Limpiar visitas con IPs locales o de prueba
    $fake_visits = $pdo->prepare("DELETE FROM app_visits WHERE 
        ip_address LIKE '127.0.0.1' OR
        ip_address LIKE '192.168.%' OR
        ip_address LIKE '10.0.%' OR
        ip_address LIKE '172.16.%' OR
        user_agent LIKE '%test%' OR
        page_url LIKE '%test%'
    ");
    $fake_visits->execute();
    $cleaned['fake_visits'] = $fake_visits->rowCount();
    
    // 3. Limpiar productos de prueba
    $fake_products = $pdo->prepare("DELETE FROM productos WHERE 
        name LIKE '%test%' OR 
        name LIKE '%prueba%' OR
        name LIKE '%demo%' OR
        sku LIKE '%TEST%' OR
        sku LIKE '%DEMO%' OR
        price = 0 OR
        price = 1
    ");
    $fake_products->execute();
    $cleaned['fake_products'] = $fake_products->rowCount();
    
    // 4. Limpiar ventas de prueba (montos sospechosos)
    $fake_sales = $pdo->prepare("DELETE FROM ventas WHERE 
        total_amount = 1 OR
        total_amount = 100 OR
        total_amount = 1000 OR
        customer_name LIKE '%test%' OR
        customer_email LIKE '%test%'
    ");
    $fake_sales->execute();
    $cleaned['fake_sales'] = $fake_sales->rowCount();
    
    // 5. Limpiar sesiones antiguas (más de 30 días)
    $old_sessions = $pdo->prepare("DELETE FROM app_visits WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $old_sessions->execute();
    $cleaned['old_sessions'] = $old_sessions->rowCount();
    
    // 6. Limpiar datos de tracking de robots (solo si las tablas existen)
    $tables_to_clean = [
        'site_visits' => "ip_address LIKE '127.0.0.1' OR ip_address LIKE '192.168.%' OR user_agent LIKE '%robot%' OR user_agent LIKE '%test%' OR user_agent LIKE '%MacIntel%'",
        'user_interactions' => "session_id LIKE '%robot%' OR session_id LIKE '%test%' OR DATE(timestamp) = CURDATE()",
        'user_journey' => "session_id LIKE '%robot%' OR session_id LIKE '%test%' OR DATE(timestamp) = CURDATE()",
        'product_analytics' => "views_count = 0 OR clicks_count = 0 OR DATE(last_interaction) = CURDATE()"
    ];
    
    foreach ($tables_to_clean as $table => $condition) {
        $check_table = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check_table->rowCount() > 0) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE $condition");
            $stmt->execute();
            $cleaned['robot_' . $table] = $stmt->rowCount();
        } else {
            $cleaned['robot_' . $table] = 0;
        }
    }
    
    // 7. Verificar y crear usuarios reales si no existen
    $real_users_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email NOT LIKE '%test%'")->fetchColumn();
    
    if ($real_users_count == 0) {
        // Crear algunos usuarios reales de ejemplo
        $create_real_user = $pdo->prepare("INSERT INTO usuarios (name, email, phone, created_at, is_active) VALUES (?, ?, ?, NOW(), 1)");
        
        $real_users = [
            ['Cliente La Ruta 11', 'cliente@laruta11.cl', '+56912345678'],
            ['María González', 'maria.gonzalez@gmail.com', '+56987654321'],
            ['Juan Pérez', 'juan.perez@outlook.com', '+56956789123']
        ];
        
        foreach ($real_users as $user) {
            $create_real_user->execute($user);
        }
        $cleaned['real_users_created'] = count($real_users);
    }
    
    // 8. Estadísticas finales después de limpieza
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn(),
        'total_products' => $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn(),
        'total_visits_today' => $pdo->query("SELECT COUNT(*) FROM app_visits WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'total_visits_week' => $pdo->query("SELECT COUNT(*) FROM app_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        'total_visits_month' => $pdo->query("SELECT COUNT(*) FROM app_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn()
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Datos falsos eliminados correctamente',
        'cleaned' => $cleaned,
        'current_stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en limpieza: ' . $e->getMessage()
    ]);
}
?>