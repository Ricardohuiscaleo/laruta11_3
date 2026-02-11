<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://app.laruta11.cl');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear trigger para auto-actualizar payment_status cuando status = 'completed'
    $trigger_sql = "
    CREATE TRIGGER auto_update_payment_status 
    BEFORE UPDATE ON tuu_orders
    FOR EACH ROW
    BEGIN
        IF NEW.status = 'completed' AND OLD.payment_status = 'unpaid' THEN
            SET NEW.payment_status = 'paid';
        END IF;
    END
    ";
    
    $pdo->exec($trigger_sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Trigger creado exitosamente',
        'description' => 'Ahora cuando status = completed, payment_status se actualiza automáticamente a paid'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'note' => 'El trigger puede ya existir'
    ]);
}
?>