<?php
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
    echo "Error: Config no encontrado";
    exit;
}

$config = include $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Debug: log de parámetros recibidos
    error_log('=== CALLBACK CONCURSO DEBUG ===');
    error_log('GET params: ' . json_encode($_GET));
    
    // Obtener parámetros de TUU
    $x_reference = $_GET['x_reference'] ?? '';
    $x_result = $_GET['x_result'] ?? '';
    $x_amount = $_GET['x_amount'] ?? '';
    $x_message = $_GET['x_message'] ?? '';
    $x_timestamp = $_GET['x_timestamp'] ?? '';
    $x_signature = $_GET['x_signature'] ?? '';
    
    error_log('Parsed params: reference=' . $x_reference . ', result=' . $x_result . ', amount=' . $x_amount);

    if (empty($x_reference)) {
        echo "Error: Referencia no encontrada";
        exit;
    }

    // Actualizar estado del pago en ambos campos
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
    
    error_log('Participante encontrado: ' . ($participante ? 'SI' : 'NO'));
    if ($participante) {
        error_log('Datos participante: ' . json_encode([
            'customer_name' => $participante['customer_name'],
            'nombre' => $participante['nombre'],
            'email' => $participante['email'],
            'customer_phone' => $participante['customer_phone'],
            'telefono' => $participante['telefono']
        ]));
    }

    if ($payment_status === 'paid' && $participante) {
        // Redirigir a página de éxito del concurso con datos completos
        $params = http_build_query([
            'order_number' => $participante['order_number'],
            'nombre' => $participante['customer_name'] ?: $participante['nombre'],
            'email' => $participante['email'],
            'telefono' => $participante['customer_phone'] ?: $participante['telefono'],
            'monto' => intval($x_amount),
            'estado' => 'exitoso',
            'mensaje' => urldecode($x_message ?: 'Pago exitoso')
        ]);
        
        header("Location: https://app.laruta11.cl/concurso/gracias/?$params");
        exit;
    } else {
        // Redirigir a página de error
        header("Location: https://app.laruta11.cl/concurso/?error=pago_fallido");
        exit;
    }

} catch (Exception $e) {
    echo "Error procesando pago: " . $e->getMessage();
}
?>