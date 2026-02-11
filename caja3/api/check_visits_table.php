<?php
header('Content-Type: application/json');

$config_paths = [
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
    echo json_encode(['error' => 'Config not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'site_visits'");
    $table_exists = $stmt->rowCount() > 0;
    
    $result = [
        'database' => $config['app_db_name'],
        'table_exists' => $table_exists
    ];
    
    if ($table_exists) {
        // Obtener estructura de la tabla
        $stmt = $pdo->query("DESCRIBE site_visits");
        $result['columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Contar registros
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM site_visits");
        $result['total_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Visitas de hoy
        $stmt = $pdo->query("SELECT COUNT(*) as today FROM site_visits WHERE DATE(created_at) = CURDATE()");
        $result['visits_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
        
        // Últimas 5 visitas
        $stmt = $pdo->query("SELECT ip_address, page_url, created_at FROM site_visits ORDER BY created_at DESC LIMIT 5");
        $result['recent_visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Crear la tabla si no existe
        $create_sql = "
        CREATE TABLE site_visits (
            id int(11) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            page_url varchar(500) NOT NULL,
            referrer varchar(500),
            session_id varchar(100),
            visit_date date NOT NULL,
            visit_time timestamp DEFAULT current_timestamp(),
            country varchar(100),
            city varchar(100),
            device_type enum('mobile','tablet','desktop') DEFAULT 'mobile',
            browser varchar(100),
            created_at timestamp DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY session_id (session_id),
            KEY visit_date (visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($create_sql);
        $result['table_created'] = true;
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>