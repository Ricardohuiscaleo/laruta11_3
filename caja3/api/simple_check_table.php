<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u958525313_Calcularuta11;charset=utf8",
        "u958525313_Calcularuta11",
        "Calcularuta11"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("DESCRIBE productos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columnas de la tabla productos:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>