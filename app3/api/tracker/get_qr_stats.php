<?php
session_start();

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

// Conectar usando config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

try {
    // Crear tabla si no existe
    $create_table = "CREATE TABLE IF NOT EXISTS qr_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        view_date DATE NOT NULL,
        view_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        user_agent TEXT,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        location_accuracy FLOAT,
        city VARCHAR(100),
        region VARCHAR(100),
        country VARCHAR(100),
        formatted_address TEXT,
        INDEX idx_date (view_date)
    )";
    mysqli_query($conn, $create_table);
    
    // Obtener estadísticas
    $today = date('Y-m-d');
    
    // Total de vistas
    $total_query = "SELECT COUNT(*) as total FROM qr_views";
    $total_result = mysqli_query($conn, $total_query);
    $total_views = mysqli_fetch_assoc($total_result)['total'];
    
    // Vistas de hoy
    $today_query = "SELECT COUNT(*) as today FROM qr_views WHERE view_date = ?";
    $today_stmt = mysqli_prepare($conn, $today_query);
    mysqli_stmt_bind_param($today_stmt, "s", $today);
    mysqli_stmt_execute($today_stmt);
    $today_result = mysqli_stmt_get_result($today_stmt);
    $today_views = mysqli_fetch_assoc($today_result)['today'];
    
    // Última vista
    $last_query = "SELECT view_time FROM qr_views ORDER BY view_time DESC LIMIT 1";
    $last_result = mysqli_query($conn, $last_query);
    $last_view = '-';
    if ($last_row = mysqli_fetch_assoc($last_result)) {
        $last_view = date('d/m/Y H:i', strtotime($last_row['view_time']));
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_views' => (int)$total_views,
            'today_views' => (int)$today_views,
            'last_view' => $last_view
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>