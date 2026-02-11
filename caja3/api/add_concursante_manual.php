<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$required = ['nombre', 'email', 'telefono', 'rut', 'peso'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Campo requerido: $field"]);
        exit;
    }
}

try {
    // Verificar RUT único
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM concurso_registros WHERE rut = ?");
    $stmt->execute([$input['rut']]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'RUT ya registrado']);
        exit;
    }
    
    // Generar order_number
    $order_number = 'MANUAL_' . date('Ymd') . '_' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO concurso_registros 
        (order_number, customer_name, nombre, rut, email, customer_phone, telefono, peso, mayor_18, payment_status, tuu_amount, image_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 5000.00, ?)
    ");
    
    $stmt->execute([
        $order_number,
        $input['nombre'],
        $input['nombre'],
        $input['rut'],
        $input['email'],
        $input['telefono'],
        $input['telefono'],
        $input['peso'],
        $input['mayor_18'] ?? 1,
        $input['payment_status'] ?? 'paid',
        $input['image_url'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'message' => 'Concursante agregado manualmente'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
?>