<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Debug register.php<br>";

try {
    // Cargar config desde raíz
    $config = require_once __DIR__ . '/../../../../config.php';
    echo "Config cargado<br>";
    
    // Conectar a BD desde config central
    $conn = mysqli_connect(
        $config['ruta11_db_host'],
        $config['ruta11_db_user'],
        $config['ruta11_db_pass'],
        $config['ruta11_db_name']
    );
    
    if (!$conn) {
        echo "Error conexión: " . mysqli_connect_error() . "<br>";
        exit();
    }
    
    echo "Conexión OK<br>";
    
    // Test INSERT
    $email = "test@test.com";
    $password = "123456";
    $nombre = "Test User";
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $insert_query = "INSERT INTO usuarios (email, password, nombre, activo) VALUES (?, ?, ?, 1)";
    $stmt = mysqli_prepare($conn, $insert_query);
    
    if (!$stmt) {
        echo "Error prepare: " . mysqli_error($conn) . "<br>";
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "sss", $email, $hashedPassword, $nombre);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "INSERT exitoso<br>";
        $userId = mysqli_insert_id($conn);
        echo "ID: " . $userId . "<br>";
        
        // Limpiar
        mysqli_query($conn, "DELETE FROM usuarios WHERE email = 'test@test.com'");
    } else {
        echo "Error INSERT: " . mysqli_error($conn) . "<br>";
    }
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}
?>