<?php
header('Content-Type: application/json');
require_once '../../../config.php';

try {
    // Verificar si las tablas existen
    $tables = ['kanban_columns', 'kanban_cards', 'kanban_history'];
    $results = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch() ? true : false;
        
        $results[$table] = [
            'exists' => $exists,
            'count' => 0,
            'structure' => []
        ];
        
        if ($exists) {
            // Contar registros
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $results[$table]['count'] = $stmt->fetch()['count'];
            
            // Ver estructura
            $stmt = $pdo->prepare("DESCRIBE $table");
            $stmt->execute();
            $results[$table]['structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    echo json_encode([
        'success' => true,
        'tables' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>