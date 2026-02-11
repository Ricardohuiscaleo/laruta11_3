<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $config_paths = [
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
        throw new Exception('Config not found');
    }

    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener todas las tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Tablas requeridas para analytics
    $required_tables = ['site_visits', 'app_users', 'user_orders', 'products'];
    
    $table_status = [];
    foreach ($required_tables as $table) {
        $exists = in_array($table, $tables);
        $table_status[$table] = [
            'exists' => $exists,
            'status' => $exists ? '✅ Existe' : '❌ No existe'
        ];
    }

    echo json_encode([
        'success' => true,
        'database' => $config['app_db_name'],
        'all_tables' => $tables,
        'required_tables' => $table_status,
        'missing_count' => count(array_filter($required_tables, fn($t) => !in_array($t, $tables)))
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>