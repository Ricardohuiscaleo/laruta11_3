<?php
header('Content-Type: application/json');

$results = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check Config File
$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
    $config = require $config_path;
    $results['checks']['config_file'] = [
        'status' => 'ok',
        'path' => $config_path
    ];
}
else {
    $results['status'] = 'error';
    $results['checks']['config_file'] = [
        'status' => 'missing',
        'path' => $config_path
    ];
}

// Check Crucial Environment Variables
$env_to_check = ['APP_DB_HOST', 'APP_DB_NAME', 'APP_DB_USER', 'REDIS_HOST'];
foreach ($env_to_check as $env) {
    $val = getenv($env);
    $results['checks']['env_vars'][$env] = empty($val) ? 'MISSING' : 'PRESENT';
}

// Check Database Connection
if (isset($config['app_db_host'])) {
    try {
        $conn = mysqli_connect(
            $config['app_db_host'],
            $config['app_db_user'],
            $config['app_db_pass'],
            $config['app_db_name']
        );
        if ($conn) {
            $results['checks']['database'] = [
                'status' => 'connected',
                'server_info' => mysqli_get_server_info($conn)
            ];

            // Check session table
            $query = "SHOW TABLES LIKE 'php_sessions'";
            $res = mysqli_query($conn, $query);
            $results['checks']['database']['session_table'] = (mysqli_num_rows($res) > 0) ? 'exists' : 'missing';

            mysqli_close($conn);
        }
        else {
            $results['status'] = 'error';
            $results['checks']['database'] = [
                'status' => 'failed',
                'error' => mysqli_connect_error()
            ];
        }
    }
    catch (Exception $e) {
        $results['status'] = 'error';
        $results['checks']['database'] = [
            'status' => 'exception',
            'message' => $e->getMessage()
        ];
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>