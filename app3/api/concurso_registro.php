<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
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
        echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

// Debug para GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status' => 'API funcionando', 'method' => 'GET permitido para debug']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
$required = ['nombre', 'email', 'telefono', 'rut', 'peso'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Campo requerido: $field"]);
        exit;
    }
}

// Validar mayor de 18
if (empty($input['mayor_18']) || $input['mayor_18'] != 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Debe ser mayor de 18 años']);
    exit;
}

try {
    // Verificar límite de 8 participantes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM concurso_registros WHERE payment_status = 'paid'");
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    if ($total >= 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Concurso lleno. Máximo 8 participantes.']);
        exit;
    }
    
    // Generar order_number único
    $order_number = 'CONCURSO_' . date('Ymd') . '_' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Verificar RUT único solo para pagos exitosos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM concurso_registros WHERE rut = ? AND payment_status = 'paid'");
    $stmt->execute([$input['rut']]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'RUT ya registrado con pago exitoso']);
        exit;
    }
    
    // Verificar si existe registro pendiente/fallido y actualizarlo
    $stmt = $pdo->prepare("SELECT id, order_number FROM concurso_registros WHERE rut = ? AND payment_status != 'paid'");
    $stmt->execute([$input['rut']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Actualizar registro existente
        $stmt = $pdo->prepare("
            UPDATE concurso_registros 
            SET customer_name = ?, nombre = ?, email = ?, customer_phone = ?, telefono = ?, peso = ?, mayor_18 = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([
            $input['nombre'],
            $input['nombre'],
            $input['email'],
            $input['telefono'],
            $input['telefono'],
            $input['peso'],
            $input['mayor_18'] ?? 1,
            $existing['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'registro_id' => $existing['id'],
            'order_number' => $existing['order_number'],
            'monto' => 5000,
            'message' => 'Registro actualizado para reintento de pago'
        ]);
        exit;
    }
    
    // Insertar registro con peso y rut
    $stmt = $pdo->prepare("
        INSERT INTO concurso_registros (order_number, customer_name, nombre, rut, email, customer_phone, telefono, peso, mayor_18, tuu_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 5000.00)
    ");
    
    $stmt->execute([
        $order_number,
        $input['nombre'], // customer_name
        $input['nombre'], // nombre (compatibilidad)
        $input['rut'],
        $input['email'],
        $input['telefono'], // customer_phone
        $input['telefono'], // telefono (compatibilidad)
        $input['peso'],
        $input['mayor_18'] ?? 1
    ]);
    
    $registro_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'registro_id' => $registro_id,
        'order_number' => $order_number,
        'monto' => 5000
    ]);
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(400);
        echo json_encode(['error' => 'Email ya registrado']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error del servidor']);
    }
}
?>