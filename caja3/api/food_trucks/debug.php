<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['error' => 'Config not found']));
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'food_trucks'");
$tableExists = $result->num_rows > 0;

// Get data if table exists
$data = [];
if ($tableExists) {
    $result = $conn->query("SELECT * FROM food_trucks");
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode([
    'config_found' => true,
    'db_connection' => 'OK',
    'table_exists' => $tableExists,
    'data_count' => count($data),
    'data' => $data
]);

$conn->close();
?>