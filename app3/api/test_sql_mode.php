<?php
header('Content-Type: application/json');

try {
    $pdo = require_once __DIR__ . '/db_connect.php';
    
    // Verificar SQL_MODE
    $stmt = $pdo->query("SELECT @@sql_mode as sql_mode");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'sql_mode' => $result['sql_mode'],
        'has_only_full_group_by' => strpos($result['sql_mode'], 'ONLY_FULL_GROUP_BY') !== false
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
