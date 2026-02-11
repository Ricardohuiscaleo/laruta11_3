<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    // Buscar config.php
    $config_paths = [
        __DIR__ . '/../../config.php',
        __DIR__ . '/../../../config.php',
        __DIR__ . '/../../../../config.php'
    ];
    
    $config_found = false;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $config_found = true;
            break;
        }
    }
    
    if (!$config_found) {
        echo json_encode(['success' => false, 'error' => 'Config not found', 'paths_tried' => $config_paths]);
        exit;
    }
    
    // Crear conexión PDO directamente
    if (!isset($pdo)) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
                "u958525313_app",
                "wEzho0-hujzoz-cevzin",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    
    // Generate order reference
    $orderRef = 'R11-' . time() . '-' . rand(1000, 9999);
    
    echo json_encode([
        'success' => true,
        'message' => 'Debug successful - Database connection OK',
        'order_reference' => $orderRef,
        'input_received' => !empty($input),
        'cart_items_count' => count($input['cart_items'] ?? []),
        'amount' => $input['amount'] ?? 0,
        'customer_name' => $input['customer_name'] ?? 'N/A',
        'customer_notes' => $input['customer_notes'] ?? null,
        'delivery_type' => $input['delivery_type'] ?? 'pickup',
        'config_found' => $config_found,
        'pdo_working' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>