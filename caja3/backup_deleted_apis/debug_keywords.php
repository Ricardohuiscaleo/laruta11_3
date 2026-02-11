<?php
// Debug para revisar keywords y cálculo de score

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

echo "<h2>🔍 Debug Keywords y Score</h2>";

// Verificar si existe la tabla
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'job_keywords'");
if (mysqli_num_rows($check_table) == 0) {
    echo "<p style='color: red;'>❌ Tabla 'job_keywords' no existe</p>";
    echo "<p>El score se está calculando sin keywords, por eso puede dar valores altos.</p>";
} else {
    echo "<p style='color: green;'>✅ Tabla 'job_keywords' existe</p>";
    
    // Mostrar keywords
    $keywords = mysqli_query($conn, "SELECT * FROM job_keywords ORDER BY position, category");
    if (mysqli_num_rows($keywords) == 0) {
        echo "<p style='color: orange;'>⚠️ No hay keywords configuradas</p>";
        echo "<p>Sin keywords, el cálculo de score no funciona correctamente.</p>";
    } else {
        echo "<h3>Keywords configuradas:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Posición</th><th>Categoría</th><th>Label</th><th>Weight</th><th>Words</th></tr>";
        
        while ($row = mysqli_fetch_assoc($keywords)) {
            $words = json_decode($row['words'], true);
            echo "<tr>";
            echo "<td>{$row['position']}</td>";
            echo "<td>{$row['category']}</td>";
            echo "<td>{$row['label']}</td>";
            echo "<td>{$row['weight']}</td>";
            echo "<td>" . implode(', ', $words) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Simular cálculo con texto de ejemplo
echo "<h3>🧮 Simulación de cálculo:</h3>";
$texto_ejemplo = "mantener la calma, iniciativa, soy responsable";
echo "<p><strong>Texto ejemplo:</strong> \"$texto_ejemplo\"</p>";

$query = "SELECT * FROM job_keywords WHERE position = 'maestro_sanguchero' OR position = 'both' ORDER BY category";
$result = mysqli_query($conn, $query);

$totalScore = 0;
$maxScorePossible = 50;
$matches = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $words = json_decode($row['words'], true);
        $weight = floatval($row['weight']);
        $count = 0;
        
        foreach ($words as $palabra) {
            if (strpos(strtolower($texto_ejemplo), $palabra) !== false) {
                $count++;
                $matches[] = $palabra;
            }
        }
        
        if ($count > 0) {
            $totalScore += $count * $weight;
            echo "<p>✅ {$row['label']}: $count palabras × $weight = " . ($count * $weight) . "</p>";
        }
    }
    
    $percentage = min(100, ($totalScore / $maxScorePossible) * 100);
    echo "<p><strong>Score total:</strong> $totalScore / $maxScorePossible = " . round($percentage, 1) . "%</p>";
    echo "<p><strong>Palabras encontradas:</strong> " . implode(', ', $matches) . "</p>";
} else {
    echo "<p style='color: red;'>❌ No se encontraron keywords para maestro_sanguchero</p>";
    echo "<p>Esto explica por qué el score puede ser incorrecto.</p>";
}

mysqli_close($conn);
?>