<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
$config = null;
foreach ([__DIR__.'/../config.php', __DIR__.'/../../config.php', __DIR__.'/../../../config.php'] as $p) {
    if (file_exists($p)) { $config = require_once $p; break; }
}
if (!$config) { echo json_encode(['success'=>false,'error'=>'Config no encontrado']); exit; }
$conn = mysqli_connect($config['ruta11_db_host'], $config['ruta11_db_user'], $config['ruta11_db_pass'], $config['ruta11_db_name']);
if (!$conn) { echo json_encode(['success'=>false,'error'=>'DB error']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'error'=>'ID requerido']); exit; }
$stmt = mysqli_prepare($conn, "DELETE FROM ajustes_sueldo WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
echo json_encode(['success' => mysqli_stmt_execute($stmt)]);
