<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
$config = null;
foreach ([__DIR__ . '/../../public/config.php', __DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../../config.php', __DIR__ . '/../../../../config.php'] as $p) {
    if (file_exists($p)) {
        $config = require_once $p;
        break;
    }
}
if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}
$conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$personal_id = intval($input['personal_id'] ?? 0);
$mes = $input['mes'] ?? '';
$monto = floatval($input['monto'] ?? 0);
$concepto = trim($input['concepto'] ?? '');
$notas = trim($input['notas'] ?? '');
if (!$personal_id || !$mes || !$concepto) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}
$stmt = mysqli_prepare($conn, "INSERT INTO ajustes_sueldo (personal_id, mes, monto, concepto, notas) VALUES (?,?,?,?,?)");
mysqli_stmt_bind_param($stmt, 'isdss', $personal_id, $mes, $monto, $concepto, $notas);
if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'id' => mysqli_insert_id($conn)]);
}
else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}