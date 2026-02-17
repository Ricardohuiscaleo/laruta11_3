<?php
// Script de diagnóstico para verificar configuración en VPS
header('Content-Type: application/json');

// Solo permitir acceso desde localhost o con parámetro de seguridad
$allowed = false;
if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || 
    $_SERVER['REMOTE_ADDR'] === '::1' || 
    $_GET['debug_key'] === 'laruta11_debug_2026') {
    $allowed = true;
}

if (!$allowed) {
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

try {
    // Cargar config actual
    $config = require_once __DIR__ . '/../config.php';
    
    $diagnostics = [
        'timestamp' => date('Y-m-d H:i:s'),
        'server_info' => [
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
        ],
        'config_method' => 'getenv',
        'environment_variables' => [],
        'config_values' => [],
        'missing_variables' => [],
        'gmail_status' => []
    ];
    
    // Variables críticas a verificar
    $critical_vars = [
        'GMAIL_CLIENT_ID',
        'GMAIL_CLIENT_SECRET', 
        'GMAIL_SENDER_EMAIL',
        'APP_DB_HOST',
        'APP_DB_NAME',
        'APP_DB_USER',
        'TUU_ONLINE_RUT',
        'TUU_ONLINE_SECRET'
    ];
    
    // Verificar variables de entorno
    foreach ($critical_vars as $var) {
        $env_value = getenv($var);
        $config_key = strtolower($var);
        
        if ($env_value !== false) {
            // Ocultar valores sensibles
            if (strpos($var, 'SECRET') !== false || strpos($var, 'PASS') !== false) {
                $diagnostics['environment_variables'][$var] = '****' . substr($env_value, -4);
            } else {
                $diagnostics['environment_variables'][$var] = $env_value;
            }
        } else {
            $diagnostics['missing_variables'][] = $var;
        }
        
        // Verificar valor en config
        if (isset($config[$config_key])) {
            if (strpos($var, 'SECRET') !== false || strpos($var, 'PASS') !== false) {
                $diagnostics['config_values'][$config_key] = '****' . substr($config[$config_key], -4);
            } else {
                $diagnostics['config_values'][$config_key] = $config[$config_key];
            }
        }
    }
    
    // Verificar Gmail token
    $gmail_token_file = __DIR__ . '/../gmail_token.json';
    if (file_exists($gmail_token_file)) {
        $token_data = json_decode(file_get_contents($gmail_token_file), true);
        $diagnostics['gmail_status'] = [
            'token_file_exists' => true,
            'has_access_token' => isset($token_data['access_token']),
            'expires_at' => $token_data['expires_at'] ?? null,
            'is_expired' => time() >= ($token_data['expires_at'] ?? 0),
            'time_to_expire' => ($token_data['expires_at'] ?? 0) - time()
        ];
    } else {
        $diagnostics['gmail_status'] = [
            'token_file_exists' => false,
            'error' => 'gmail_token.json no encontrado'
        ];
    }
    
    // Verificar conexión a base de datos
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $diagnostics['database_status'] = [
            'connection' => 'OK',
            'host' => $config['app_db_host'],
            'database' => $config['app_db_name']
        ];
    } catch (Exception $e) {
        $diagnostics['database_status'] = [
            'connection' => 'ERROR',
            'error' => $e->getMessage()
        ];
    }
    
    // Verificar archivos críticos
    $critical_files = [
        'config.php' => __DIR__ . '/../config.php',
        'gmail_token.json' => __DIR__ . '/../gmail_token.json',
        '.env' => __DIR__ . '/../../.env'
    ];
    
    $diagnostics['file_status'] = [];
    foreach ($critical_files as $name => $path) {
        $diagnostics['file_status'][$name] = [
            'exists' => file_exists($path),
            'readable' => file_exists($path) && is_readable($path),
            'path' => $path
        ];
    }
    
    echo json_encode($diagnostics, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error en diagnóstico: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>