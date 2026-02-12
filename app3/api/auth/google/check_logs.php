<?php
echo "<h1>Debug Logs</h1>";

$log_file = __DIR__ . '/callback_debug.log';
if (file_exists($log_file)) {
    echo "<h2>Callback Debug Log:</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents($log_file)) . "</pre>";
} else {
    echo "<p>No callback debug log found</p>";
}

$oauth_log = __DIR__ . '/oauth_debug.log';
if (file_exists($oauth_log)) {
    echo "<h2>OAuth Debug Log:</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents($oauth_log)) . "</pre>";
} else {
    echo "<p>No oauth debug log found</p>";
}

// Test directo de conexión DB
echo "<hr><h2>Test DB Connections:</h2>";
$config = require_once __DIR__ . '/../../../config.php';

// Test usuarios DB
$user_conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if ($user_conn) {
    echo "<p>✅ Users DB Connection: SUCCESS</p>";
    echo "<p>Database: " . $config['ruta11_db_name'] . "</p>";
    mysqli_close($user_conn);
} else {
    echo "<p>❌ Users DB Connection: FAILED - " . mysqli_connect_error() . "</p>";
}

// Test app DB
$app_conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if ($app_conn) {
    echo "<p>✅ App DB Connection: SUCCESS</p>";
    echo "<p>Database: " . $config['app_db_name'] . "</p>";
    mysqli_close($app_conn);
} else {
    echo "<p>❌ App DB Connection: FAILED - " . mysqli_connect_error() . "</p>";
}
?>