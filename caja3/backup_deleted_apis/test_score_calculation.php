<?php
// Test del cálculo de score con texto real

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

// Texto de ejemplo del registro con score 290
$texto_real = "mantener la calma, iniciativa, soy responsable, ve manten la calma y seguridad soy reopsnablem y obvi al final preparan mi carte de renuncia con limpiez";

echo "<h2>🧮 Test de Cálculo de Score</h2>";
echo "<p><strong>Texto:</strong> \"$texto_real\"</p>";

$position = 'maestro_sanguchero';
$query = "SELECT * FROM job_keywords WHERE position = ? OR position = 'both' ORDER BY category";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $position);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$totalScore = 0;
$maxScorePossible = 120; // Valor balanceado
$skillsDetected = [];

echo "<h3>Análisis por categoría:</h3>";

while ($row = mysqli_fetch_assoc($result)) {
    $words = json_decode($row['words'], true);
    $weight = floatval($row['weight']);
    $label = $row['label'];
    $count = 0;
    $found_words = [];
    
    foreach ($words as $palabra) {
        if (strpos(strtolower($texto_real), $palabra) !== false) {
            $count++;
            $found_words[] = $palabra;
        }
    }
    
    if ($count > 0) {
        $categoryScore = $count * $weight;
        $totalScore += $categoryScore;
        $skillsDetected[$row['category']] = [
            'count' => $count,
            'label' => $label
        ];
        
        echo "<div style='background: #f0f9ff; padding: 10px; margin: 5px 0; border-left: 4px solid #0ea5e9;'>";
        echo "<strong>{$label}</strong><br>";
        echo "Palabras encontradas: " . implode(', ', $found_words) . "<br>";
        echo "Cálculo: $count palabras × $weight peso = <strong>$categoryScore puntos</strong>";
        echo "</div>";
    }
}

$percentage = min(100, ($totalScore / $maxScorePossible) * 100);

echo "<h3>📊 Resultado Final:</h3>";
echo "<div style='background: #f0fdf4; padding: 15px; border-left: 4px solid #22c55e;'>";
echo "<strong>Score total:</strong> $totalScore puntos<br>";
echo "<strong>Máximo posible:</strong> $maxScorePossible puntos<br>";
echo "<strong>Porcentaje:</strong> " . round($percentage, 1) . "%<br>";
echo "<strong>Limitado a:</strong> " . min(100, round($percentage, 1)) . "%";
echo "</div>";

// Comparar con múltiples valores para encontrar el óptimo
echo "<h3>⚖️ Comparación de Escalas:</h3>";
$test_values = [50, 80, 100, 120, 150, 200];
foreach ($test_values as $test_max) {
    $test_percentage = min(100, ($totalScore / $test_max) * 100);
    $color = $test_max == $maxScorePossible ? '#22c55e' : '#6b7280';
    $current = $test_max == $maxScorePossible ? ' (ACTUAL)' : '';
    echo "<p style='color: $color;'><strong>maxScore = $test_max:</strong> " . round($test_percentage, 1) . "%$current</p>";
}

mysqli_close($conn);
?>