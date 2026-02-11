<?php
require_once 'config.php';

try {
    // SQL directo para migrar productos de categoría 8 a tabla combos
    $sql = "
        INSERT INTO combos (name, description, price, image_url, category_id, active, created_at)
        SELECT 
            name,
            COALESCE(description, '') as description,
            price,
            COALESCE(image_url, '') as image_url,
            8 as category_id,
            1 as active,
            NOW() as created_at
        FROM productos 
        WHERE category_id = 8 
        AND active = 1
        AND name NOT IN (SELECT name FROM combos)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $migrated = $stmt->rowCount();
    
    // Contar total de productos combo existentes
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE category_id = 8 AND active = 1");
    $count_stmt->execute();
    $total = $count_stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => "Migrados $migrated combos de $total productos existentes",
        'migrated' => $migrated,
        'total_found' => $total
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>