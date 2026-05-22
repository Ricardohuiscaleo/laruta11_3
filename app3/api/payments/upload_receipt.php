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

    require_once __DIR__ . '/../S3Manager.php';
    $s3 = new S3Manager($config);
    $receipt_url = $s3->uploadFile($_FILES['receipt'], $objectKey, false);

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
