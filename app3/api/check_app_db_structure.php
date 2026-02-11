<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u958525313_app;charset=utf8",
        "u958525313_app",
        "wEzho0-hujzoz-cevzin"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ESTRUCTURA DE LA BASE DE DATOS u958525313_app ===\n\n";
    
    // Verificar si existe la tabla productos
    $stmt = $pdo->query("SHOW TABLES LIKE 'productos'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'productos' encontrada\n\n";
        
        $stmt = $pdo->query("DESCRIBE productos");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Columnas de la tabla productos:\n";
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    } else {
        echo "❌ Tabla 'productos' NO encontrada\n";
        
        // Mostrar todas las tablas disponibles
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "\nTablas disponibles en u958525313_app:\n";
        foreach ($tables as $table) {
            echo "- " . $table . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>