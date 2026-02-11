<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

header('Content-Type: application/json');

try {
    // Agregar columnas faltantes a la tabla usuarios
    $columns_to_add = [
        "ADD COLUMN google_id VARCHAR(255) UNIQUE",
        "ADD COLUMN email VARCHAR(255)",
        "ADD COLUMN foto_perfil TEXT",
        "ADD COLUMN fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "ADD COLUMN ultimo_acceso TIMESTAMP",
        "ADD COLUMN telefono VARCHAR(20)",
        "ADD COLUMN instagram VARCHAR(100)",
        "ADD COLUMN lugar_nacimiento VARCHAR(255)",
        "ADD COLUMN genero ENUM('masculino', 'femenino', 'otro', 'no_decir')",
        "ADD COLUMN fecha_nacimiento DATE",
        "ADD COLUMN latitud DECIMAL(10, 8)",
        "ADD COLUMN longitud DECIMAL(11, 8)",
        "ADD COLUMN direccion_actual TEXT",
        "ADD COLUMN ubicacion_actualizada TIMESTAMP"
    ];
    
    $results = [];
    
    foreach ($columns_to_add as $column_sql) {
        $query = "ALTER TABLE usuarios $column_sql";
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            $results[] = "✅ " . $column_sql;
        } else {
            $error = mysqli_error($conn);
            if (strpos($error, 'Duplicate column name') !== false) {
                $results[] = "⚠️ " . $column_sql . " (ya existe)";
            } else {
                $results[] = "❌ " . $column_sql . " - Error: " . $error;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>