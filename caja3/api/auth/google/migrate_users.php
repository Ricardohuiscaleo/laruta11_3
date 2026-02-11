<?php
$config = require_once __DIR__ . '/../../../../../config.php';

// Conectar a ambas BDs
$user_conn = mysqli_connect($config['ruta11_db_host'], $config['ruta11_db_user'], $config['ruta11_db_pass'], $config['ruta11_db_name']);
$app_conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

echo "<h1>Migrar Usuarios a App DB</h1>";

// Obtener usuarios con google_id de la BD principal
$result = mysqli_query($user_conn, "SELECT * FROM usuarios WHERE google_id IS NOT NULL");
$migrated = 0;
$errors = 0;

echo "<p>Migrando usuarios...</p>";

while ($user = mysqli_fetch_assoc($result)) {
    // Verificar si ya existe en app DB
    $check = mysqli_query($app_conn, "SELECT id FROM usuarios WHERE google_id = '" . mysqli_real_escape_string($app_conn, $user['google_id']) . "'");
    
    if (mysqli_num_rows($check) == 0) {
        // No existe, crear
        $sql = "INSERT INTO usuarios (google_id, nombre, email, foto_perfil, fecha_registro, ultimo_acceso, activo) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($app_conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssi", 
            $user['google_id'], 
            $user['nombre'], 
            $user['email'], 
            $user['foto_perfil'], 
            $user['fecha_registro'], 
            $user['ultimo_acceso'], 
            $user['activo']
        );
        
        if (mysqli_stmt_execute($stmt)) {
            echo "<p>✅ Migrado: {$user['nombre']} ({$user['email']})</p>";
            $migrated++;
        } else {
            echo "<p>❌ Error: {$user['nombre']} - " . mysqli_error($app_conn) . "</p>";
            $errors++;
        }
    } else {
        echo "<p>⚠️ Ya existe: {$user['nombre']} ({$user['email']})</p>";
    }
}

echo "<hr>";
echo "<h2>Resumen:</h2>";
echo "<p>✅ Usuarios migrados: $migrated</p>";
echo "<p>❌ Errores: $errors</p>";

mysqli_close($user_conn);
mysqli_close($app_conn);
?>