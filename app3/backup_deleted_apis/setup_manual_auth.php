<?php
$config = require_once __DIR__ . '/../../../../config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar si la tabla usuarios existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Crear tabla usuarios
        $sql = "CREATE TABLE usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NULL,
            nombre VARCHAR(255) NOT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            google_id VARCHAR(255) NULL,
            foto_perfil VARCHAR(500) NULL
        )";
        $pdo->exec($sql);
        echo "✅ Tabla usuarios creada exitosamente\n";
    } else {
        echo "✅ Tabla usuarios ya existe\n";
        
        // Verificar si existe la columna password
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'password'");
        $passwordColumnExists = $stmt->rowCount() > 0;
        
        if (!$passwordColumnExists) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN password VARCHAR(255) NULL AFTER email");
            echo "✅ Columna password agregada a la tabla usuarios\n";
        } else {
            echo "✅ Columna password ya existe\n";
        }
        
        // Verificar si existe la columna fecha_registro
        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'fecha_registro'");
        $fechaColumnExists = $stmt->rowCount() > 0;
        
        if (!$fechaColumnExists) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "✅ Columna fecha_registro agregada a la tabla usuarios\n";
        } else {
            echo "✅ Columna fecha_registro ya existe\n";
        }
    }

    echo "\n🎉 Configuración completada. El sistema de autenticación manual está listo.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>