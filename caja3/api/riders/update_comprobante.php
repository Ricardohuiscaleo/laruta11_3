<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$config = null;
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../public/config.php'] as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $riderId = intval($_POST['rider_id'] ?? 0);
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $comprobanteUrl = $_POST['comprobante_url'] ?? null;

    if (!$riderId || !$startDate || !$endDate || !$comprobanteUrl) {
        throw new Exception('rider_id, start_date, end_date y comprobante_url requeridos');
    }

    $stmt = $pdo->prepare("
        UPDATE rider_pagos
        SET comprobante_url = ?
        WHERE rider_id = ?
          AND fecha >= ?
          AND fecha <= ?
          AND estado = 'pagado'
    ");
    $stmt->execute([$comprobanteUrl, $riderId, $startDate, $endDate]);
    $updated = $stmt->rowCount();

    echo json_encode(['success' => true, 'updated' => $updated]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
