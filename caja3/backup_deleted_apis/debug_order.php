<?php
if (file_exists(__DIR__ . '/../config.php')) {
    $config = require_once __DIR__ . '/../config.php';
} else {
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No se encontró el archivo de configuración']);
        exit;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'tuu_orders'");
    $tableExists = $stmt->rowCount() > 0;
    
    // Obtener estructura de la tabla si existe
    $tableStructure = [];
    if ($tableExists) {
        $stmt = $pdo->query("DESCRIBE tuu_orders");
        $tableStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Test de inserción simple
    $testData = [
        'order_number' => 'TEST' . time(),
        'customer_name' => 'Test Cliente',
        'product_name' => 'Test Producto',
        'product_price' => 1000.00,
        'installment_amount' => 500.00
    ];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sql = "INSERT INTO tuu_orders (
            order_number,
            customer_name, 
            product_name,
            product_price,
            installment_amount
        ) VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $testData['order_number'],
            $testData['customer_name'],
            $testData['product_name'],
            $testData['product_price'],
            $testData['installment_amount']
        ]);
        
        $insertId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Test insert exitoso',
            'insert_id' => $insertId,
            'test_data' => $testData
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'table_exists' => $tableExists,
            'table_structure' => $tableStructure,
            'database' => $config['app_db_name'],
            'test_data' => $testData,
            'message' => 'Debug info - POST para test insert'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>