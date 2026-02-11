<?php
header('Content-Type: application/json');

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

    // Agregar columnas para datos de TUU si no existen
    $columns_to_add = [
        'tuu_amount' => 'DECIMAL(10,2) NULL',
        'tuu_timestamp' => 'VARCHAR(255) NULL',
        'tuu_message' => 'VARCHAR(255) NULL',
        'tuu_account_id' => 'VARCHAR(50) NULL',
        'tuu_currency' => 'VARCHAR(10) NULL',
        'tuu_signature' => 'TEXT NULL'
    ];

    foreach ($columns_to_add as $column => $definition) {
        try {
            $sql = "ALTER TABLE tuu_orders ADD COLUMN $column $definition";
            $pdo->exec($sql);
            echo "✅ Columna $column agregada\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ️ Columna $column ya existe\n";
            } else {
                throw $e;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Estructura de tabla actualizada para capturar todos los datos de TUU'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>