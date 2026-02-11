<?php
header('Content-Type: application/json');
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../config.php';

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

$position = $_GET['position'] ?? 'maestro_sanguchero';

try {
    $query = "SELECT * FROM job_keywords WHERE position = ? OR position = 'both' ORDER BY category";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $position);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $keywords = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $words = json_decode($row['words'], true);
        // Ofuscar palabras clave
        $hashedWords = [];
        foreach ($words as $word) {
            $hashedWords[] = hash('sha256', $word . 'salt_ruta11_2024');
        }
        
        $keywords[$row['category']] = [
            'words' => $hashedWords,
            'weight' => floatval($row['weight']),
            'label' => $row['label']
        ];
    }
    
    echo json_encode(['success' => true, 'keywords' => $keywords]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>