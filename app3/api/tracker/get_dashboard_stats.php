<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config = require_once __DIR__ . '/../../config.php';

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
    // Obtener postulantes por día (26 Jul - 7 Ago)
    $postulantes_query = "
        SELECT 
            DATE(created_at) as fecha,
            COUNT(*) as postulantes
        FROM job_applications 
        WHERE DATE(created_at) BETWEEN '2025-07-26' AND '2025-08-07'
        GROUP BY DATE(created_at)
        ORDER BY fecha ASC
    ";
    
    $postulantes_result = mysqli_query($conn, $postulantes_query);
    
    // Obtener vistas QR por día (26 Jul - 7 Ago)
    $visitas_query = "
        SELECT 
            view_date as fecha,
            COUNT(*) as visitas
        FROM qr_views 
        WHERE view_date BETWEEN '2025-07-26' AND '2025-08-07'
        GROUP BY view_date
        ORDER BY fecha ASC
    ";
    
    $visitas_result = mysqli_query($conn, $visitas_query);
    
    // Procesar datos de postulantes
    $postulantes_data = [];
    while ($row = mysqli_fetch_assoc($postulantes_result)) {
        $postulantes_data[$row['fecha']] = (int)$row['postulantes'];
    }
    
    // Procesar datos de visitas
    $visitas_data = [];
    while ($row = mysqli_fetch_assoc($visitas_result)) {
        $visitas_data[$row['fecha']] = (int)$row['visitas'];
    }
    
    // Generar fechas del 26 Jul al 7 Ago 2025
    $dates = [];
    $postulantes = [];
    $visitas = [];
    
    $fechas_periodo = [
        '2025-07-26', '2025-07-27', '2025-07-28', '2025-07-29', '2025-07-30', '2025-07-31',
        '2025-08-01', '2025-08-02', '2025-08-03', '2025-08-04', '2025-08-05', '2025-08-06', '2025-08-07'
    ];
    
    foreach ($fechas_periodo as $fecha) {
        $dates[] = date('j M', strtotime($fecha));
        $postulantes[] = $postulantes_data[$fecha] ?? 0;
        $visitas[] = $visitas_data[$fecha] ?? 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'dates' => $dates,
            'postulantes' => $postulantes,
            'visitas' => $visitas
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