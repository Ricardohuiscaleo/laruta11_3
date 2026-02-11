<?php
// Ejemplo práctico de sincronización usando códigos en común

if (file_exists(__DIR__ . '/../config.php')) {
    $config = require_once __DIR__ . '/../config.php';
} else {
    exit('Config not found');
}

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // PASO 1: Obtener datos de registro (MySQL)
    $orderId = $_GET['order_id'] ?? 10;
    
    $sqlMySQL = "SELECT 
        id,
        customer_name,
        customer_phone,
        table_number,
        product_name,
        product_price,
        installment_amount,
        tuu_payment_request_id,
        tuu_idempotency_key,
        status,
        created_at
    FROM tuu_orders WHERE id = ?";
    
    $stmt = $pdo->prepare($sqlMySQL);
    $stmt->execute([$orderId]);
    $registroMySQL = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registroMySQL) {
        throw new Exception("Pedido #$orderId no encontrado");
    }
    
    // PASO 2: Obtener datos de pago (TUU) usando CÓDIGO EN COMÚN
    $paymentRequestId = $registroMySQL['tuu_payment_request_id'];
    $datosTUU = null;
    
    if ($paymentRequestId) {
        $url = "https://integrations.payment.haulmer.com/RemotePayment/v2/GetPaymentRequest/{$paymentRequestId}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $config['tuu_api_key']
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if ($responseData && $responseData['code'] === '200') {
                $datosTUU = $responseData['content'];
            }
        }
    }
    
    // PASO 3: SINCRONIZAR - Combinar datos usando códigos en común
    $datosSincronizados = [
        // DATOS DE REGISTRO (MySQL)
        'registro' => [
            'order_id' => $registroMySQL['id'],
            'customer_name' => $registroMySQL['customer_name'],
            'customer_phone' => $registroMySQL['customer_phone'],
            'table_number' => $registroMySQL['table_number'],
            'product_name' => $registroMySQL['product_name'],
            'product_price' => $registroMySQL['product_price'],
            'installment_amount' => $registroMySQL['installment_amount'],
            'created_at' => $registroMySQL['created_at']
        ],
        
        // CÓDIGOS EN COMÚN (Claves de conexión)
        'codigos_conexion' => [
            'payment_request_id' => $registroMySQL['tuu_payment_request_id'], // CLAVE PRINCIPAL
            'idempotency_key' => $registroMySQL['tuu_idempotency_key'],        // CLAVE NUESTRA
            'transaction_id' => $datosTUU['transactionId'] ?? null,           // CLAVE TUU
        ],
        
        // DATOS DE PAGO (TUU)
        'pago' => $datosTUU ? [
            'transaction_id' => $datosTUU['transactionId'] ?? null,
            'authorization_code' => $datosTUU['authorizationCode'] ?? null,
            'card_number' => $datosTUU['cardNumber'] ?? null,
            'sequence_number' => $datosTUU['sequenceNumber'] ?? null,
            'status' => $datosTUU['status'] ?? null,
            'transaction_date' => $datosTUU['transactionDate'] ?? null,
            'amount' => $datosTUU['amount'] ?? null
        ] : null,
        
        // DATOS COMBINADOS (Resultado final)
        'datos_completos' => [
            'id_unico_completo' => $registroMySQL['id'] . '_' . ($datosTUU['transactionId'] ?? 'pending'),
            'cliente_identificado' => $registroMySQL['customer_name'] . ' (' . ($datosTUU['cardNumber'] ?? 'sin tarjeta') . ')',
            'pago_verificado' => $datosTUU ? ($datosTUU['status'] == 5 ? 'PAGADO' : 'PENDIENTE') : 'SIN DATOS',
            'monto_confirmado' => $registroMySQL['installment_amount'] == ($datosTUU['amount'] ?? 0) ? 'CORRECTO' : 'DIFERENCIA'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'sincronizacion' => $datosSincronizados,
        'codigos_en_comun' => [
            'payment_request_id' => $registroMySQL['tuu_payment_request_id'],
            'idempotency_key' => $registroMySQL['tuu_idempotency_key'],
            'transaction_id' => $datosTUU['transactionId'] ?? null
        ],
        'explicacion' => [
            'como_funciona' => 'Usamos payment_request_id como código en común para conectar registro MySQL con pago TUU',
            'claves_conexion' => 'payment_request_id + idempotency_key + transaction_id',
            'resultado' => 'Datos completos del cliente + datos verificados del pago'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>