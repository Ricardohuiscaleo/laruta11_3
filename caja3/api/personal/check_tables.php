<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$config = null;
foreach ([__DIR__.'/../../public/config.php', __DIR__.'/../config.php', __DIR__.'/../../config.php', __DIR__.'/../../../config.php', __DIR__.'/../../../../config.php'] as $p) {
    if (file_exists($p)) { $config = require_once $p; break; }
}
if (!$config) { echo json_encode(['error'=>'Config no encontrado']); exit; }
$conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if (!$conn) { echo json_encode(['error'=>'DB error: '.mysqli_connect_error()]); exit; }

$tables = ['personal', 'turnos', 'ajustes_sueldo', 'turnos_excluidos'];
$result = [];

foreach ($tables as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($check) === 0) {
        $result[$table] = 'NO EXISTE';
        continue;
    }
    $cols = mysqli_query($conn, "DESCRIBE $table");
    $result[$table] = [];
    while ($col = mysqli_fetch_assoc($cols)) $result[$table][] = $col;
}

echo json_encode($result, JSON_PRETTY_PRINT);
