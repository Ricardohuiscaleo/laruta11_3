<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

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

// Verificar si la columna ya existe
$check = mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'session_token'");
if (mysqli_num_rows($check) == 0) {
    $sql = "ALTER TABLE usuarios ADD COLUMN session_token VARCHAR(64) NULL";
    if (mysqli_query($conn, $sql)) {
        echo "✅ Columna session_token agregada exitosamente";
    } else {
        echo "❌ Error: " . mysqli_error($conn);
    }
} else {
    echo "✅ Columna session_token ya existe";
}

mysqli_close($conn);
?>