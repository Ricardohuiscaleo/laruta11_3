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
        echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['transaction_id']) || empty($input['estado'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos']);
    exit;
}

try {
    // Actualizar registro del concurso con nueva estructura
    $payment_status = $input['estado'] === 'success' ? 'paid' : 'failed';
    
    $stmt = $pdo->prepare("
        UPDATE concurso_registros 
        SET payment_status = ?,
            tuu_transaction_id = ?,
            tuu_amount = ?,
            tuu_timestamp = ?,
            tuu_message = ?,
            updated_at = NOW()
        WHERE order_number = ?
    ");
    
    $stmt->execute([
        $payment_status,
        $input['transaction_id'] ?? null,
        $input['amount'] ?? 5000,
        $input['timestamp'] ?? date('Y-m-d H:i:s'),
        $input['message'] ?? null,
        $input['order_number'] ?? $input['transaction_id']
    ]);
    
    // Si el pago fue exitoso, redirigir a página de gracias
    if ($payment_status === 'paid') {
        // Obtener datos del participante
        $stmt = $pdo->prepare("SELECT * FROM concurso_registros WHERE order_number = ?");
        $stmt->execute([$input['order_number'] ?? $input['transaction_id']]);
        $participante = $stmt->fetch();
        
        if ($participante) {
            // Construir URL con parámetros
            $params = http_build_query([
                'order_number' => $participante['order_number'],
                'customer_name' => $participante['customer_name'],
                'email' => $participante['email'],
                'customer_phone' => $participante['customer_phone'],
                'fecha_nacimiento' => $participante['fecha_nacimiento'],
                'tuu_amount' => $participante['tuu_amount'],
                'payment_status' => 'paid',
                'estado_pago' => 'exitoso',
                'transaction_id' => $input['transaction_id']
            ]);
            
            // Redirigir a página de gracias
            header("Location: /concurso/gracias/?$params");
            exit;
        }
    }
    
    echo json_encode([
        'success' => true,
        'payment_status' => $payment_status
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error procesando pago']);
}
?>