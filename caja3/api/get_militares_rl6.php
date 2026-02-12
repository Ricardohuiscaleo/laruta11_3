<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $conn = new mysqli(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );
    
    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
    
    $status = $_GET['status'] ?? 'pending';
    
    $sql = "SELECT 
                id,
                nombre as name,
                email,
                telefono as phone,
                rut as rut_militar,
                grado_militar as rango_militar,
                unidad_trabajo as unidad_militar,
                carnet_frontal_url as carnet_militar_url,
                carnet_trasero_url,
                selfie_url as selfie_carnet_url,
                credito_aprobado,
                limite_credito,
                (limite_credito - credito_usado) as credito_disponible,
                credito_usado,
                fecha_aprobacion_rl6 as fecha_aprobacion_credito,
                NULL as aprobado_por_admin_id,
                fecha_registro as registration_date,
                ultimo_acceso as last_login
            FROM usuarios 
            WHERE is_militar_rl6 = 1";
    
    if ($status === 'pending') {
        $sql .= " AND credito_aprobado = 0";
    } elseif ($status === 'approved') {
        $sql .= " AND credito_aprobado = 1";
    }
    
    $sql .= " ORDER BY fecha_solicitud_rl6 DESC";
    
    $result = $conn->query($sql);
    $militares = [];
    
    while ($row = $result->fetch_assoc()) {
        $militares[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $militares,
        'count' => count($militares)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
