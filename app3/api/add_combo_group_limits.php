<?php
require_once 'config.php';

try {
    // Agregar columna max_selections a combo_selections
    $sql = "ALTER TABLE combo_selections ADD COLUMN max_selections INT DEFAULT 1";
    $pdo->exec($sql);
    
    echo json_encode(['success' => true, 'message' => 'Campo max_selections agregado']);
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo json_encode(['success' => true, 'message' => 'Campo ya existe']);
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>