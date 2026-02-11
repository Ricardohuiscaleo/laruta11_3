<?php
header('Content-Type: application/json');
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Configurar conexión a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión a BD']));
}

mysqli_set_charset($conn, 'utf8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$texto = strtolower($_POST['texto'] ?? '');
$position = $_POST['position'] ?? 'maestro_sanguchero';

if (empty($texto)) {
    echo json_encode(['success' => true, 'percentage' => 0, 'skills' => []]);
    exit();
}

try {
    // Cargar keywords desde MySQL
    $query = "SELECT * FROM job_keywords WHERE position = ? OR position = 'both' ORDER BY category";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $position);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $totalScore = 0;
    $skillsDetected = [];
    $maxScorePossible = 55; // Calibrado con datos reales: score actual = ~90%
    
    while ($row = mysqli_fetch_assoc($result)) {
        $words = json_decode($row['words'], true);
        $weight = floatval($row['weight']);
        $label = $row['label'];
        $count = 0;
        
        foreach ($words as $palabra) {
            if (strpos($texto, $palabra) !== false) {
                $count++;
            }
        }
        
        if ($count > 0) {
            $totalScore += $count * $weight;
            $skillsDetected[$row['category']] = [
                'count' => $count,
                'label' => $label
            ];
        }
    }
    
    $percentage = min(100, ($totalScore / $maxScorePossible) * 100);
    
    echo json_encode([
        'success' => true,
        'percentage' => $percentage,
        'skills' => $skillsDetected
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>