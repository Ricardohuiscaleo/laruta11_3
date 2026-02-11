<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

$config = include $configPath;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $x_reference = $input['x_reference'] ?? '';
    $x_result = $input['x_result'] ?? '';
    $x_amount = $input['x_amount'] ?? '';
    $x_message = $input['x_message'] ?? '';
    $x_timestamp = $input['x_timestamp'] ?? '';
    $x_signature = $input['x_signature'] ?? '';

    if (empty($x_reference)) {
        echo json_encode(['success' => false, 'error' => 'x_reference requerido']);
        exit;
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Actualizar estado del pago
    $payment_status = ($x_result === 'completed') ? 'paid' : 'failed';
    $estado_pago = ($x_result === 'completed') ? 'pagado' : 'fallido';
    
    $stmt = $pdo->prepare("
        UPDATE concurso_registros 
        SET payment_status = ?, 
            estado_pago = ?,
            tuu_transaction_id = ?, 
            tuu_timestamp = ?,
            tuu_message = ?,
            tuu_signature = ?,
            fecha_pago = NOW(),
            updated_at = NOW()
        WHERE order_number = ?
    ");
    $stmt->execute([$payment_status, $estado_pago, $x_signature, $x_timestamp, $x_message, $x_signature, $x_reference]);

    // Obtener datos del participante
    $stmt = $pdo->prepare("SELECT * FROM concurso_registros WHERE order_number = ?");
    $stmt->execute([$x_reference]);
    $participante = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($participante) {
        echo json_encode([
            'success' => true,
            'participante' => [
                'order_number' => $participante['order_number'],
                'nombre' => $participante['customer_name'] ?: $participante['nombre'],
                'email' => $participante['email'],
                'telefono' => $participante['customer_phone'] ?: $participante['telefono'],
                'payment_status' => $payment_status
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Participante no encontrado']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>