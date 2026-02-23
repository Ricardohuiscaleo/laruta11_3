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
$mes = $_GET['mes'] ?? date('Y-m');
$mesDate = $mes . '-01';
$stmt = mysqli_prepare($conn, "SELECT * FROM pagos_nomina WHERE mes = ? ORDER BY es_externo, nombre");
mysqli_stmt_bind_param($stmt, 's', $mesDate);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = [];
while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
$cfg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT config_value FROM system_config WHERE config_key = 'presupuesto_nomina'"));
$presupuesto = $cfg ? floatval($cfg['config_value']) : 1200000;
echo json_encode(['success'=>true,'data'=>$data,'presupuesto'=>$presupuesto]);
