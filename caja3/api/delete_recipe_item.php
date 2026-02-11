<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID requerido']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM product_recipes WHERE id = ?");
$success = $stmt->execute([$id]);

echo json_encode(['success' => $success]);
