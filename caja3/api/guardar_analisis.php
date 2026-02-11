<?php
// Función para guardar el análisis en la base de datos
function guardarAnalisis($conn, $tipo, $contenido) {
    // Verificar si la tabla existe
    $checkTable = $conn->query("SHOW TABLES LIKE 'ia_analisis'");
    if ($checkTable->num_rows == 0) {
        // Crear la tabla si no existe
        $createTable = "CREATE TABLE ia_analisis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('descriptivo', 'diagnostico', 'predictivo', 'prescriptivo') NOT NULL,
            contenido LONGTEXT NOT NULL,
            fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($createTable);
    }
    
    // Insertar el nuevo análisis
    $sql = "INSERT INTO ia_analisis (tipo, contenido) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $tipo, $contenido);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}
?>