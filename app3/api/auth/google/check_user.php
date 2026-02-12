<?php
$config = require_once __DIR__ . '/../../../config.php';

// Conectar a ambas BDs
$user_conn = mysqli_connect($config['ruta11_db_host'], $config['ruta11_db_user'], $config['ruta11_db_pass'], $config['ruta11_db_name']);
$app_conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

echo "<h1>Verificar Usuarios con Google ID</h1>";

// Buscar usuarios con google_id no nulo en users DB
$result1 = mysqli_query($user_conn, "SELECT id, google_id, nombre, email, fecha_registro FROM usuarios WHERE google_id IS NOT NULL ORDER BY fecha_registro DESC LIMIT 5");
echo "<h2>Usuarios en u958525313_usuariosruta11:</h2>";
if (mysqli_num_rows($result1) > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Google ID</th><th>Nombre</th><th>Email</th><th>Fecha</th></tr>";
    while ($row = mysqli_fetch_assoc($result1)) {
        echo "<tr><td>{$row['id']}</td><td>" . substr($row['google_id'], 0, 15) . "...</td><td>{$row['nombre']}</td><td>{$row['email']}</td><td>{$row['fecha_registro']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay usuarios con Google ID</p>";
}

// Buscar usuarios con google_id no nulo en app DB
$result2 = mysqli_query($app_conn, "SELECT id, google_id, nombre, email, fecha_registro FROM usuarios WHERE google_id IS NOT NULL ORDER BY fecha_registro DESC LIMIT 5");
echo "<h2>Usuarios en u958525313_app:</h2>";
if (mysqli_num_rows($result2) > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Google ID</th><th>Nombre</th><th>Email</th><th>Fecha</th></tr>";
    while ($row = mysqli_fetch_assoc($result2)) {
        echo "<tr><td>{$row['id']}</td><td>" . substr($row['google_id'], 0, 15) . "...</td><td>{$row['nombre']}</td><td>{$row['email']}</td><td>{$row['fecha_registro']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No hay usuarios con Google ID</p>";
}

mysqli_close($user_conn);
mysqli_close($app_conn);
?>