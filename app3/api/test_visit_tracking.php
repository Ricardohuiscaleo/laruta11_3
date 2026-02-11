<?php
// Test visit tracking
header('Content-Type: application/json');

echo "Testing visit tracking...\n\n";

// Simular una visita
$test_data = [
    'page_url' => 'https://app.laruta11.cl',
    'session_id' => 'test-' . time()
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://app.laruta11.cl/api/app/track_visit.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n\n";

// Verificar en base de datos
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if ($config) {
    try {
        $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM site_visits WHERE DATE(created_at) = CURDATE()");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Visitas hoy en BD: " . $result['total'] . "\n";
        
        // Últimas 3 visitas
        $stmt = $pdo->query("SELECT * FROM site_visits ORDER BY created_at DESC LIMIT 3");
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nÚltimas 3 visitas:\n";
        foreach ($visits as $visit) {
            echo "- {$visit['ip_address']} | {$visit['page_url']} | {$visit['created_at']}\n";
        }
        
    } catch (Exception $e) {
        echo "Error BD: " . $e->getMessage() . "\n";
    }
} else {
    echo "Config no encontrado\n";
}
?>