<?php
// Buscar config.php
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    throw new Exception('Config file not found');
}

// Usar variables de entorno directamente si config no las tiene
$db_host = $config['app_db_host'] ?? getenv('APP_DB_HOST') ?? 'localhost';
$db_name = $config['app_db_name'] ?? getenv('APP_DB_NAME') ?? 'laruta11';
$db_user = $config['app_db_user'] ?? getenv('APP_DB_USER') ?? 'root';
$db_pass = $config['app_db_pass'] ?? getenv('APP_DB_PASS') ?? '';

// Crear conexión PDO con SQL_MODE configurado
$pdo = new PDO(
    "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
    $db_user,
    $db_pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Configurar SQL_MODE sin ONLY_FULL_GROUP_BY
$pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

return $pdo;
?>
