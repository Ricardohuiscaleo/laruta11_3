<?php
// Script para corregir las fechas completed_at que están mal

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
    die('Error de conexión a BD');
}

mysqli_set_charset($conn, 'utf8');

echo "<h2>🔧 Corrección de fechas completed_at</h2>";

// Mostrar registros con completed_at
$query = "SELECT id, completed_at, created_at, updated_at FROM job_applications WHERE completed_at IS NOT NULL ORDER BY completed_at DESC";
$result = mysqli_query($conn, $query);

echo "<h3>Registros actuales:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>completed_at</th><th>created_at</th><th>updated_at</th><th>Acción</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    $completed = new DateTime($row['completed_at']);
    $updated = new DateTime($row['updated_at']);
    
    echo "<tr>";
    echo "<td>" . substr($row['id'], -8) . "</td>";
    echo "<td>{$row['completed_at']}</td>";
    echo "<td>{$row['created_at']}</td>";
    echo "<td>{$row['updated_at']}</td>";
    
    // Si completed_at es diferente de updated_at, corregir
    if ($completed->format('Y-m-d H:i:s') !== $updated->format('Y-m-d H:i:s')) {
        echo "<td style='color: orange;'>Corregir a updated_at</td>";
        
        // Actualizar completed_at = updated_at
        $update_sql = "UPDATE job_applications SET completed_at = updated_at WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "s", $row['id']);
        mysqli_stmt_execute($stmt);
    } else {
        echo "<td style='color: green;'>OK</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Mostrar zona horaria del servidor
echo "<h3>Información del servidor:</h3>";
echo "<p><strong>Zona horaria PHP:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>Fecha/hora actual PHP:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Fecha/hora actual MySQL:</strong> ";

$mysql_time = mysqli_query($conn, "SELECT NOW() as current_time");
$time_row = mysqli_fetch_assoc($mysql_time);
echo $time_row['current_time'] . "</p>";

mysqli_close($conn);
?>