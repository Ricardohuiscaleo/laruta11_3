<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../config.php',
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
    $input = json_decode(file_get_contents('php://input'), true);
    $opened_by = $input['opened_by'] ?? 'Cajero';
    $opening_notes = $input['opening_notes'] ?? null;
    $shift_type = 'night'; // Siempre turno noche
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar si la tabla existe, si no, crearla
    $check_table = "SHOW TABLES LIKE 'cash_register_sessions'";
    $result = $pdo->query($check_table);
    
    if ($result->rowCount() === 0) {
        $create_sql = "CREATE TABLE IF NOT EXISTS cash_register_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_date DATE NOT NULL,
            opened_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            opened_by VARCHAR(100) NOT NULL,
            closed_by VARCHAR(100) NULL,
            cash_total DECIMAL(10,2) DEFAULT 0,
            cash_count INT DEFAULT 0,
            card_total DECIMAL(10,2) DEFAULT 0,
            card_count INT DEFAULT 0,
            transfer_total DECIMAL(10,2) DEFAULT 0,
            transfer_count INT DEFAULT 0,
            pedidosya_total DECIMAL(10,2) DEFAULT 0,
            pedidosya_count INT DEFAULT 0,
            webpay_total DECIMAL(10,2) DEFAULT 0,
            webpay_count INT DEFAULT 0,
            total_amount DECIMAL(10,2) DEFAULT 0,
            total_orders INT DEFAULT 0,
            status ENUM('open', 'closed') DEFAULT 'open',
            opening_notes TEXT NULL,
            closing_notes TEXT NULL,
            whatsapp_sent TINYINT(1) DEFAULT 0,
            whatsapp_sent_at DATETIME NULL,
            shift_type ENUM('day', 'night') DEFAULT 'day',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_session_date (session_date),
            INDEX idx_status (status),
            INDEX idx_opened_at (opened_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $pdo->exec($create_sql);
    }
    
    // Verificar si ya hay una sesión abierta hoy
    $today = date('Y-m-d');
    $check_sql = "SELECT id FROM cash_register_sessions WHERE session_date = ? AND status = 'open'";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$today]);
    
    if ($check_stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'error' => 'Ya existe una sesión de caja abierta para hoy'
        ]);
        exit;
    }
    
    // Crear nueva sesión
    $sql = "INSERT INTO cash_register_sessions (
        session_date, opened_at, opened_by, opening_notes, status, shift_type
    ) VALUES (?, NOW(), ?, ?, 'open', ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today, $opened_by, $opening_notes, $shift_type]);
    
    $session_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'message' => 'Caja abierta exitosamente'
    ]);
    
} catch (Exception $e) {
    error_log("Open Cash Register Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
