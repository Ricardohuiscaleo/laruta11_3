<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$config = null;
foreach ([__DIR__.'/../../public/config.php', __DIR__.'/../config.php', __DIR__.'/../../config.php', __DIR__.'/../../../config.php', __DIR__.'/../../../../config.php'] as $p) {
    if (file_exists($p)) { $config = require_once $p; break; }
}
if (!$config) { echo json_encode(['success'=>false,'error'=>'Config no encontrado']); exit; }
$conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if (!$conn) { echo json_encode(['success'=>false,'error'=>'DB error']); exit; }
$res = mysqli_query($conn, "SELECT * FROM personal WHERE activo=1 ORDER BY rol, nombre");
$data = [];
while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
echo json_encode(['success'=>true,'data'=>$data]);
