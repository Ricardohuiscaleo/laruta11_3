<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Config file not found']));
}

// Crear conexión usando la configuración de app
$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

// Obtener customer_name de la sesión
session_start();
$customer_name = $_SESSION['user_name'] ?? $_SESSION['nombre'] ?? null;

// Si no hay nombre en sesión, usar email para buscar en tuu_orders
if (!$customer_name && isset($_SESSION['email'])) {
    $email_sql = "SELECT DISTINCT customer_name FROM tuu_orders WHERE customer_name LIKE ? LIMIT 1";
    $email_stmt = $conn->prepare($email_sql);
    $search_term = '%' . $_SESSION['email'] . '%';
    $email_stmt->bind_param('s', $search_term);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    if ($email_row = $email_result->fetch_assoc()) {
        $customer_name = $email_row['customer_name'];
    }
    $email_stmt->close();
}

// Para debug: usar directamente Ricardo Huiscaleo si no hay sesión
if (!$customer_name) {
    $customer_name = 'Ricardo Huiscaleo';
}

try {
    // Obtener notificaciones del usuario con detalles del pedido
    $sql = "SELECT 
                n.id,
                n.order_number,
                n.customer_name,
                n.message,
                n.status,
                n.is_read,
                n.created_at,
                o.product_name,
                o.tuu_amount as order_total
            FROM order_notifications n
            LEFT JOIN tuu_orders o ON n.order_number = o.order_number
            WHERE n.customer_name = ?
            ORDER BY n.created_at DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $customer_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    
    // Contar notificaciones no leídas
    $unread_sql = "SELECT COUNT(*) as unread_count FROM order_notifications WHERE customer_name = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_sql);
    $unread_stmt->bind_param('s', $customer_name);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $unread_count = $unread_data['unread_count'];
    
    // Formatear notificaciones
    $formatted_notifications = array_map(function($notif) {
        return [
            'id' => intval($notif['id']),
            'order_number' => $notif['order_number'],
            'message' => $notif['message'],
            'status' => $notif['status'],
            'is_read' => intval($notif['is_read']),
            'created_at' => $notif['created_at'],
            'product_name' => $notif['product_name'], // Incluir detalles del producto
            'order_total' => $notif['order_total']
        ];
    }, $notifications);
    
    $stmt->close();
    $unread_stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications,
        'unread_count' => $unread_count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>