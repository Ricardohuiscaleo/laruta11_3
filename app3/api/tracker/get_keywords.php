<?php
session_start();
header('Content-Type: application/json');

// Cache busting headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Verificar autenticación
if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

// Conectar a BD
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

try {
    // Verificar si tabla job_keywords existe, si no crearla
    $check_table = "SHOW TABLES LIKE 'job_keywords'";
    $table_exists = mysqli_query($conn, $check_table);
    
    if (mysqli_num_rows($table_exists) == 0) {
        // Crear tabla job_keywords
        $create_table = "
            CREATE TABLE job_keywords (
                id INT AUTO_INCREMENT PRIMARY KEY,
                keyword VARCHAR(255) NOT NULL,
                weight DECIMAL(3,1) DEFAULT 1.0,
                position ENUM('maestro_sanguchero', 'cajero') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_position (position)
            )
        ";
        mysqli_query($conn, $create_table);
        
        // Insertar keywords por defecto
        $default_keywords = [
            ['experiencia', 2.0, 'maestro_sanguchero'],
            ['cocina', 2.5, 'maestro_sanguchero'],
            ['sandwich', 2.0, 'maestro_sanguchero'],
            ['limpieza', 1.5, 'maestro_sanguchero'],
            ['trabajo en equipo', 1.5, 'maestro_sanguchero'],
            ['atención al cliente', 2.0, 'cajero'],
            ['dinero', 2.0, 'cajero'],
            ['caja', 2.5, 'cajero'],
            ['matemáticas', 1.5, 'cajero'],
            ['responsable', 1.5, 'cajero']
        ];
        
        foreach ($default_keywords as $kw) {
            $insert = "INSERT INTO job_keywords (keyword, weight, position) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert);
            mysqli_stmt_bind_param($stmt, "sds", $kw[0], $kw[1], $kw[2]);
            mysqli_stmt_execute($stmt);
        }
    }
    
    // Obtener keywords con estructura JSON original
    $keywords_query = "SELECT * FROM job_keywords ORDER BY position, category";
    $result = mysqli_query($conn, $keywords_query);
    
    $keywords = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Decodificar JSON words
        if ($row['words']) {
            $row['words'] = json_decode($row['words'], true);
        }
        $keywords[] = $row;
    }
    
    // Calcular estadísticas en tiempo real
    $stats_query = "
        SELECT 
            position,
            COUNT(DISTINCT user_id) as total_candidates,
            AVG(score) as avg_score,
            COUNT(*) as total_applications
        FROM job_applications 
        GROUP BY position
    ";
    
    $stats_result = mysqli_query($conn, $stats_query);
    $stats = [];
    while ($row = mysqli_fetch_assoc($stats_result)) {
        $stats[$row['position']] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'keywords' => $keywords,
            'stats' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>