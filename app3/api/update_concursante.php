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

$required = ['id', 'nombre', 'email', 'telefono', 'rut', 'peso'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Campo requerido: $field"]);
        exit;
    }
}

try {
    // Verificar RUT único (excluyendo el registro actual)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM concurso_registros WHERE rut = ? AND id != ?");
    $stmt->execute([$input['rut'], $input['id']]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'RUT ya registrado por otro concursante']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        UPDATE concurso_registros 
        SET customer_name = ?, nombre = ?, rut = ?, email = ?, customer_phone = ?, telefono = ?, peso = ?, payment_status = ?, image_url = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([
        $input['nombre'],
        $input['nombre'],
        $input['rut'],
        $input['email'],
        $input['telefono'],
        $input['telefono'],
        $input['peso'],
        $input['payment_status'],
        $input['image_url'] ?? null,
        $input['id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Concursante actualizado'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
?>