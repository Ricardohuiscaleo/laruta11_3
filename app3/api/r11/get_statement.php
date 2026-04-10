<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

// CORS restringido
$allowed_origins = ['https://app.laruta11.cl', 'https://caja.laruta11.cl'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Configuración no encontrada']));
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión']));
}

$conn->set_charset('utf8mb4');

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

// SEGURIDAD: Validar session_token y que user_id coincida
$session_token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE['session_token'] ?? null;
if (!$session_token) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM usuarios WHERE session_token = ? AND activo = 1");
$stmt->bind_param("s", $session_token);
$stmt->execute();
$auth_user = $stmt->get_result()->fetch_assoc();

if (!$auth_user || $auth_user['id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Obtener datos del usuario R11
$stmt = $conn->prepare("
    SELECT nombre, email, relacion_r11, limite_credito_r11, credito_r11_usado, fecha_aprobacion_r11
    FROM usuarios 
    WHERE id = ? AND es_credito_r11 = 1 AND credito_r11_aprobado = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o sin crédito R11 aprobado']);
    exit;
}

// Obtener transacciones del mes actual de r11_credit_transactions
$stmt = $conn->prepare("
    SELECT 
        id,
        amount,
        type,
        description,
        order_id,
        created_at
    FROM r11_credit_transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $items = [];

    // Si tiene order_id, obtener items de la venta y datos de delivery
    if ($row['order_id']) {
        // Items de productos
        $stmt_items = $conn->prepare("
            SELECT product_name, quantity, product_price, combo_data
            FROM tuu_order_items
            WHERE order_reference = ?
        ");
        $stmt_items->bind_param("s", $row['order_id']);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();

        while ($item = $result_items->fetch_assoc()) {
            $items[] = [
                'nombre' => $item['product_name'],
                'cantidad' => intval($item['quantity']),
                'precio' => floatval($item['product_price'])
            ];

            // Parsear customizaciones si existen
            if ($item['combo_data']) {
                $decoded = html_entity_decode($item['combo_data'], ENT_QUOTES, 'UTF-8');
                $combo_data = json_decode($decoded, true);
                if (isset($combo_data['customizations']) && is_array($combo_data['customizations'])) {
                    foreach ($combo_data['customizations'] as $custom) {
                        $items[] = [
                            'nombre' => '  + ' . ($custom['name'] ?? 'Extra'),
                            'cantidad' => intval($custom['quantity'] ?? 1),
                            'precio' => floatval($custom['price'] ?? 0)
                        ];
                    }
                }
            }
        }

        // Datos de delivery/extras
        $stmt_order = $conn->prepare("
            SELECT delivery_type, delivery_fee, delivery_discount, delivery_extras, delivery_extras_items, subtotal
            FROM tuu_orders
            WHERE order_number = ?
        ");
        $stmt_order->bind_param("s", $row['order_id']);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        $order_data = $result_order->fetch_assoc();

        $delivery_fee = 0;
        $delivery_discount = 0;
        $subtotal_productos = 0;

        if ($order_data && floatval($order_data['delivery_fee']) > 0) {
            $delivery_fee = floatval($order_data['delivery_fee']);
            $delivery_discount = floatval($order_data['delivery_discount'] ?? 0);
            $delivery_neto = $delivery_fee - $delivery_discount;

            $items[] = [
                'nombre' => 'Delivery (' . ucfirst($order_data['delivery_type']) . ')',
                'cantidad' => 1,
                'precio' => $delivery_neto
            ];
        }

        if ($order_data) {
            $subtotal_productos = floatval($order_data['subtotal']) - ($delivery_fee - $delivery_discount);
        }

        if ($order_data && floatval($order_data['delivery_extras']) > 0 && $order_data['delivery_extras_items']) {
            $extras = json_decode($order_data['delivery_extras_items'], true);
            if ($extras && is_array($extras)) {
                foreach ($extras as $extra) {
                    $items[] = [
                        'nombre' => $extra['name'] ?? 'Extra',
                        'cantidad' => 1,
                        'precio' => floatval($extra['price'] ?? 0)
                    ];
                }
            }
        }
    }

    $transactions[] = [
        'id' => $row['id'],
        'fecha' => $row['created_at'],
        'monto' => floatval($row['amount']),
        'tipo' => $row['type'],
        'descripcion' => $row['description'],
        'order_id' => $row['order_id'],
        'items' => $items,
        'subtotal_productos' => $subtotal_productos ?? 0,
        'delivery_fee' => ($delivery_fee ?? 0) - ($delivery_discount ?? 0),
        'delivery_discount' => $delivery_discount ?? 0
    ];
}

echo json_encode([
    'success' => true,
    'user' => [
        'nombre' => $user['nombre'],
        'email' => $user['email'],
        'relacion_r11' => $user['relacion_r11'],
        'credito_total' => floatval($user['limite_credito_r11']),
        'credito_usado' => floatval($user['credito_r11_usado']),
        'credito_disponible' => floatval($user['limite_credito_r11']) - floatval($user['credito_r11_usado']),
        'fecha_aprobacion' => $user['fecha_aprobacion_r11']
    ],
    'transactions' => $transactions,
    'total_transactions' => count($transactions)
]);

$conn->close();
?>
