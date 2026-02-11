<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php hasta 5 niveles
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if ($configPath) {
    $config = include $configPath;
    // Usar conexión desde config
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}



try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            order_number,
            COALESCE(customer_name, nombre) as nombre, 
            rut,
            email,
            COALESCE(customer_phone, telefono) as telefono,
            peso,
            mayor_18,
            payment_status,
            estado_pago,
            tuu_amount,
            fecha_registro as created_at,
            fecha_pago,
            image_url
        FROM concurso_registros 
        ORDER BY fecha_registro ASC
    ");
    $stmt->execute();
    $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar solo los pagados para cupos disponibles
    $pagados = array_filter($participantes, function($p) {
        return $p['payment_status'] === 'paid' || $p['estado_pago'] === 'pagado';
    });
    
    echo json_encode([
        'success' => true,
        'participantes' => $participantes,
        'total' => count($participantes),
        'pagados' => count($pagados),
        'disponibles' => 8 - count($pagados)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>