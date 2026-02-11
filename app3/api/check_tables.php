<?php
$config = require_once __DIR__ . '/../../../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tablas existentes:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}