<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG CALLBACK ===\n\n";

echo "1. __DIR__: " . __DIR__ . "\n";
echo "2. Ruta config: " . __DIR__ . '/../../../config.php' . "\n";
echo "3. Config existe: " . (file_exists(__DIR__ . '/../../../config.php') ? 'YES' : 'NO') . "\n";
echo "4. Config readable: " . (is_readable(__DIR__ . '/../../../config.php') ? 'YES' : 'NO') . "\n\n";

echo "5. Intentando cargar config...\n";
try {
    $config = require_once __DIR__ . '/../../../config.php';
    echo "6. Config cargado OK\n";
    echo "7. CLIENT_ID: " . ($config['ruta11_google_client_id'] ?? 'NOT SET') . "\n";
} catch (Exception $e) {
    echo "6. ERROR: " . $e->getMessage() . "\n";
}
