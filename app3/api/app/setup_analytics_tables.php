<?php
// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php', // 4 niveles
    __DIR__ . '/../../../../../config.php' // 5 niveles
];

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Usar base de datos app
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Leer y ejecutar el archivo SQL
    $sql_file = __DIR__ . '/setup_analytics_tables.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception('SQL file not found');
    }

    $sql_content = file_get_contents($sql_file);
    
    // Dividir en statements individuales
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    $results = [];
    
    foreach ($statements as $statement) {
        if (trim($statement)) {
            try {
                $pdo->exec($statement);
                $results[] = "✓ Executed successfully";
            } catch (Exception $e) {
                $results[] = "✗ Error: " . $e->getMessage();
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Analytics tables setup completed',
        'details' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>