<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log de debug
error_log("DEBUG: Script iniciado");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("DEBUG: Método no es POST: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
error_log("DEBUG: Input recibido: " . json_encode($input));

try {
    // Test básico de conexión
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
        "u958525313_app",
        "wEzho0-hujzoz-cevzin",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    error_log("DEBUG: Conexión DB exitosa");
    
    // Generar order reference
    $orderRef = 'DEBUG-' . time() . '-' . rand(1000, 9999);
    error_log("DEBUG: Order ref generado: " . $orderRef);
    
    // Respuesta de éxito para debug
    echo json_encode([
        'success' => true,
        'debug' => true,
        'message' => 'Debug successful - datos recibidos correctamente',
        'order_reference' => $orderRef,
        'amount' => $input['amount'] ?? 0,
        'customer_name' => $input['customer_name'] ?? 'N/A',
        'customer_notes' => $input['customer_notes'] ?? null,
        'cart_items_count' => count($input['cart_items'] ?? [])
    ]);
    
} catch (Exception $e) {
    error_log("DEBUG: Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>