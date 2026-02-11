<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php'
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

    // Verificar estructura actual
    $describe_sql = "DESCRIBE tuu_orders";
    $columns = $pdo->query($describe_sql)->fetchAll(PDO::FETCH_COLUMN);
    
    $missing_columns = [];
    $required_columns = [
        'user_id' => 'INT NULL',
        'amount' => 'DECIMAL(10,2) NOT NULL',
        'customer_name' => 'VARCHAR(255) NOT NULL',
        'customer_email' => 'VARCHAR(255) NULL',
        'customer_phone' => 'VARCHAR(50) NULL',
        'status' => 'VARCHAR(50) DEFAULT "pending"'
    ];
    
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $columns)) {
            $missing_columns[$column] = $definition;
        }
    }
    
    if (empty($missing_columns)) {
        echo json_encode([
            'success' => true,
            'message' => 'Todas las columnas ya existen',
            'current_columns' => $columns
        ]);
        exit;
    }
    
    // Agregar columnas faltantes
    foreach ($missing_columns as $column => $definition) {
        $alter_sql = "ALTER TABLE tuu_orders ADD COLUMN $column $definition";
        $pdo->exec($alter_sql);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Columnas agregadas exitosamente',
        'added_columns' => array_keys($missing_columns),
        'current_columns' => $pdo->query($describe_sql)->fetchAll(PDO::FETCH_COLUMN)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>