<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo "Error de conexión: " . mysqli_connect_error();
    exit();
}

echo "Conexión exitosa<br>";

// Verificar tabla usuarios
$result = mysqli_query($conn, "SHOW TABLES LIKE 'usuarios'");
if (mysqli_num_rows($result) > 0) {
    echo "Tabla usuarios existe<br>";
    
    // Verificar columnas
    $columns = mysqli_query($conn, "SHOW COLUMNS FROM usuarios");
    echo "Columnas:<br>";
    while ($col = mysqli_fetch_assoc($columns)) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
} else {
    echo "Tabla usuarios NO existe<br>";
}

mysqli_close($conn);
?>