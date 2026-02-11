<?php
// Crear tabla para cache de sincronización TUU

// Buscar config.php hasta 5 niveles hacia la raíz
$configPaths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php', 
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$configFound = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    die('config.php no encontrado');
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tuu_sync_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            total_revenue DECIMAL(15,2) DEFAULT 0,
            total_transactions INT DEFAULT 0,
            avg_ticket DECIMAL(10,2) DEFAULT 0,
            sync_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            raw_data JSON,
            UNIQUE KEY unique_sync (DATE(sync_date))
        )
    ");
    
    echo "✅ Tabla tuu_sync_cache creada correctamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>