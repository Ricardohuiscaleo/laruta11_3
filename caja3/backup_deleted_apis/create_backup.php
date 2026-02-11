<?php
// API para crear un respaldo de la base de datos
header('Content-Type: application/json');
require_once '../config.php';

// Crear directorio de respaldos si no existe
$backup_dir = '../backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Nombre del archivo de respaldo
$date = date('Y-m-d_H-i-s');
$backup_file = "$backup_dir/backup_$date.sql";

// Comando para crear el respaldo
$command = "mysqldump --user=" . DB_USERNAME . " --password=" . DB_PASSWORD . " --host=" . DB_SERVER . " " . DB_NAME . " > $backup_file";

// Ejecutar el comando
$output = [];
$return_var = 0;
exec($command, $output, $return_var);

if ($return_var === 0) {
    // Registrar el respaldo en la base de datos
    $query = "SHOW TABLES LIKE 'backups'";
    $result = mysqli_query($conn, $query);
    $tabla_existe = $result && mysqli_num_rows($result) > 0;
    
    if (!$tabla_existe) {
        // Crear la tabla de respaldos
        $query = "CREATE TABLE backups (
            id INT(11) NOT NULL AUTO_INCREMENT,
            fecha DATETIME NOT NULL,
            archivo VARCHAR(255) NOT NULL,
            PRIMARY KEY (id)
        )";
        
        mysqli_query($conn, $query);
    }
    
    // Insertar el registro del respaldo
    $query = "INSERT INTO backups (fecha, archivo) VALUES (NOW(), '$backup_file')";
    mysqli_query($conn, $query);
    
    echo json_encode([
        'success' => true,
        'message' => 'Respaldo creado correctamente',
        'file' => $backup_file
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error al crear el respaldo'
    ]);
}
?>
