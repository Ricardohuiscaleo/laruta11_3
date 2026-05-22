<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}
if (!$config) { die(json_encode(['success' => false, 'error' => 'Config no encontrado'])); }

try {
    $user_id = $_POST['user_id'] ?? null;
    $monto = $_POST['monto'] ?? null;
    $tipo = $_POST['tipo'] ?? 'rl6';

    if (!$user_id || !$monto) {
        throw new Exception('Faltan datos requeridos (user_id, monto)');
    }
    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo');
    }

    $file = $_FILES['receipt'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    if (!in_array($file['type'], $allowed)) {
        throw new Exception('Tipo de archivo no permitido. Usa JPG, PNG, WEBP o PDF');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo no puede superar los 10MB');
    }

    $order_number = 'TRF-' . time() . '-' . strtoupper(substr(uniqid(), -6));
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: match ($file['type']) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        default => 'bin',
    };
    $objectKey = 'receipts/' . $order_number . '_' . time() . '.' . $ext;

    // Upload to Cloudflare R2 via S3-compatible API with SigV4
    $body = file_get_contents($file['tmp_name']);
    $mimeType = $file['type'];
    $endpoint = rtrim($config['s3_endpoint'], '/');
    $bucket = $config['s3_bucket'];
    $region = $config['s3_region'] ?? 'auto';
    $accessKey = $config['aws_access_key_id'];
    $secretKey = $config['aws_secret_access_key'];

    $url = "{$endpoint}/{$bucket}/{$objectKey}";
    $host = parse_url($url, PHP_URL_HOST);
    $uri = parse_url($url, PHP_URL_PATH);
    $amzDate = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    $payloadHash = hash('sha256', $body);
    $service = 's3';

    $canonicalHeaders = "content-type:{$mimeType}\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
    $canonicalRequest = "PUT\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
    $algorithm = 'AWS4-HMAC-SHA256';
    $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
    $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

    $kDate = hash_hmac('sha256', $date, "AWS4{$secretKey}", true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    $authorization = "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: {$mimeType}",
            "x-amz-content-sha256: {$payloadHash}",
            "x-amz-date: {$amzDate}",
            "Authorization: {$authorization}",
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) throw new Exception("Error cURL subiendo a R2: {$curlError}");
    if ($httpCode !== 200 && $httpCode !== 204) {
        throw new Exception("Error HTTP {$httpCode} subiendo a R2: " . substr($response, 0, 200));
    }

    $receipt_url = rtrim($config['s3_url'], '/') . '/' . $objectKey;

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get user info for the order
    $user_sql = "SELECT nombre, telefono, grado_militar FROM usuarios WHERE id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }

    $tipo_label = strtoupper($tipo);
    $product_name = "Pago Crédito {$tipo_label} - {$user['grado_militar']}";

    $insert = $pdo->prepare("INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone,
        product_name, product_price, installment_amount, delivery_fee,
        status, payment_status, payment_method, order_status, delivery_type,
        receipt_path, receipt_original_name, receipt_status, subtotal,
        discount_amount, discount_10, discount_30, discount_birthday,
        discount_pizza, delivery_discount, delivery_extras, cashback_used,
        card_surcharge, pagado_con_credito_rl6, monto_credito_rl6,
        pagado_con_credito_r11, monto_credito_r11
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0,
        'pending', 'unpaid', 'transfer', 'pending', 'pickup',
        ?, ?, 'pending_review', 0,
        0, 0, 0, 0,
        0, 0, 0, 0,
        0, ?, ?, ?, ?)");

    $insert->execute([
        $order_number,
        $user_id,
        $user['nombre'],
        $user['telefono'] ?? '',
        $product_name,
        $monto,
        $monto,
        $receipt_url,
        $file['name'],
        $tipo === 'rl6' ? 1 : 0,
        $tipo === 'rl6' ? $monto : 0,
        $tipo === 'r11' ? 1 : 0,
        $tipo === 'r11' ? $monto : 0,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Comprobante recibido. Queda en revisión.',
        'order_number' => $order_number
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
