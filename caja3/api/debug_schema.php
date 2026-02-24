<?php
header('Content-Type: application/json');
try {
    $pdo = require_once __DIR__ . '/db_connect.php';

    $tables = ['inventory_transactions', 'ingredients', 'products'];
    $result = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $result[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success' => true, 'schema' => $result]);
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}