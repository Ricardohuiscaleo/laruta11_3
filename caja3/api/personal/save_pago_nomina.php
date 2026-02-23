<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
$config = null;
foreach ([__DIR__.'/../../public/config.php', __DIR__.'/../config.php', __DIR__.'/../../config.php', __DIR__.'/../../../config.php', __DIR__.'/../../../../config.php'] as $p) {
    if (file_exists($p)) { $config = require_once $p; break; }
}
if (!$config) { echo json_encode(['success'=>false,'error'=>'Config no encontrado']); exit; }
$conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if (!$conn) { echo json_encode(['success'=>false,'error'=>'DB error']); exit; }
$body = json_decode(file_get_contents('php://input'), true);
$mes = ($body['mes'] ?? date('Y-m')) . '-01';
$personal_id = $body['personal_id'] ?? null;
$nombre = trim($body['nombre'] ?? '');
$monto = floatval($body['monto'] ?? 0);
$es_externo = intval($body['es_externo'] ?? 0);
$notas = trim($body['notas'] ?? '');
if (!$nombre || !$monto) { echo json_encode(['success'=>false,'error'=>'Faltan datos']); exit; }
$stmt = mysqli_prepare($conn,
    "INSERT INTO pagos_nomina (mes, personal_id, nombre, monto, es_externo, notas)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE monto=VALUES(monto), notas=VALUES(notas)"
);
mysqli_stmt_bind_param($stmt, 'sissss', $mes, $personal_id, $nombre, $monto, $es_externo, $notas);
mysqli_stmt_execute($stmt);
echo json_encode(['success'=>true,'id'=>mysqli_insert_id($conn)]);
