<?php
header('Content-Type: application/json');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'config_status' => [],
    'critical_apis' => [],
    'callbacks' => [],
    'pending_pages' => []
];

// 1. Verificar config.php
$config_files = [
    'root' => __DIR__ . '/../config.php',
    'public' => __DIR__ . '/config.php',
    'loader' => __DIR__ . '/../config_loader.php'
];

foreach ($config_files as $name => $path) {
    $results['config_status'][$name] = [
        'exists' => file_exists($path),
        'readable' => file_exists($path) && is_readable($path),
        'path' => $path
    ];
}

// 2. Verificar APIs críticas
$critical_apis = [
    'check_config' => __DIR__ . '/check_config.php',
    'get_pending_orders' => __DIR__ . '/get_pending_orders.php',
    'get_productos' => __DIR__ . '/get_productos.php',
    'registrar_venta' => __DIR__ . '/registrar_venta.php',
    'get_ingredientes' => __DIR__ . '/get_ingredientes.php'
];

foreach ($critical_apis as $name => $path) {
    $exists = file_exists($path);
    $can_load_config = false;
    
    if ($exists) {
        $content = file_get_contents($path);
        $can_load_config = (
            strpos($content, 'config.php') !== false ||
            strpos($content, 'config_loader.php') !== false
        );
    }
    
    $results['critical_apis'][$name] = [
        'exists' => $exists,
        'loads_config' => $can_load_config,
        'path' => $path
    ];
}

// 3. Verificar callbacks
$callbacks = [
    'tuu_callback' => __DIR__ . '/tuu/callback.php',
    'tuu_online_callback' => __DIR__ . '/tuu-pagos-online/callback.php',
    'concurso_callback' => __DIR__ . '/concurso_pago_callback.php',
    'google_callback' => __DIR__ . '/auth/google/callback.php'
];

foreach ($callbacks as $name => $path) {
    $exists = file_exists($path);
    $can_load_config = false;
    
    if ($exists) {
        $content = file_get_contents($path);
        $can_load_config = (
            strpos($content, 'config.php') !== false ||
            strpos($content, 'config_loader.php') !== false
        );
    }
    
    $results['callbacks'][$name] = [
        'exists' => $exists,
        'loads_config' => $can_load_config,
        'path' => $path
    ];
}

// 4. Test de carga real
try {
    $config = require __DIR__ . '/../config.php';
    $results['config_load_test'] = [
        'success' => true,
        'is_array' => is_array($config),
        'keys_count' => is_array($config) ? count($config) : 0,
        'has_db_config' => isset($config['ruta11_db_host'])
    ];
} catch (Exception $e) {
    $results['config_load_test'] = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// 5. Test de conexión a BD
if (isset($config) && is_array($config)) {
    try {
        $conn = @mysqli_connect(
            $config['ruta11_db_host'],
            $config['ruta11_db_user'],
            $config['ruta11_db_pass'],
            $config['ruta11_db_name']
        );
        
        $results['database_test'] = [
            'connected' => $conn !== false,
            'error' => $conn ? null : mysqli_connect_error()
        ];
        
        if ($conn) {
            mysqli_close($conn);
        }
    } catch (Exception $e) {
        $results['database_test'] = [
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Resumen
$all_configs_ok = array_reduce($results['config_status'], function($carry, $item) {
    return $carry && $item['exists'] && $item['readable'];
}, true);

$all_apis_ok = array_reduce($results['critical_apis'], function($carry, $item) {
    return $carry && $item['exists'] && $item['loads_config'];
}, true);

$all_callbacks_ok = array_reduce($results['callbacks'], function($carry, $item) {
    return $carry && $item['exists'] && $item['loads_config'];
}, true);

$results['summary'] = [
    'all_configs_ok' => $all_configs_ok,
    'all_apis_ok' => $all_apis_ok,
    'all_callbacks_ok' => $all_callbacks_ok,
    'config_loads' => $results['config_load_test']['success'] ?? false,
    'database_connects' => $results['database_test']['connected'] ?? false,
    'overall_status' => (
        $all_configs_ok && 
        $all_apis_ok && 
        $all_callbacks_ok && 
        ($results['config_load_test']['success'] ?? false) &&
        ($results['database_test']['connected'] ?? false)
    ) ? 'OK' : 'ISSUES_FOUND'
];

echo json_encode($results, JSON_PRETTY_PRINT);
?>
