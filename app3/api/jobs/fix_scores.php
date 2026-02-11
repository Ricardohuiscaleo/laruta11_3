<?php
// Script para corregir scores > 100 en la base de datos

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

// Corregir scores > 100
$sql = "UPDATE job_applications SET score = 100 WHERE score > 100";

if (mysqli_query($conn, $sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "✅ Corregidos $affected registros con score > 100%<br>";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br>";
}

// Mostrar estadísticas
$stats = mysqli_query($conn, "SELECT 
    COUNT(*) as total,
    AVG(score) as promedio,
    MIN(score) as minimo,
    MAX(score) as maximo
    FROM job_applications WHERE score > 0");

if ($row = mysqli_fetch_assoc($stats)) {
    echo "<br><strong>Estadísticas actualizadas:</strong><br>";
    echo "Total registros: {$row['total']}<br>";
    echo "Score promedio: " . round($row['promedio'], 1) . "%<br>";
    echo "Score mínimo: {$row['minimo']}%<br>";
    echo "Score máximo: {$row['maximo']}%<br>";
}

mysqli_close($conn);
?>