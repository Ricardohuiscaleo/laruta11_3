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
$inicio = $mes . '-01';
$fin = date('Y-m-t', strtotime($inicio));

$stmt = mysqli_prepare($conn, "
    SELECT t.*, r.nombre as reemplazante_nombre
    FROM turnos t
    LEFT JOIN personal r ON r.id = t.reemplazado_por
    WHERE t.fecha BETWEEN ? AND ?
    ORDER BY t.fecha, t.personal_id
");
mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fin);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = [];
while ($row = mysqli_fetch_assoc($res)) $data[] = $row;

echo json_encode(['success' => true, 'data' => $data]);
