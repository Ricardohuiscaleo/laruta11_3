<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Tabla de compras
    $pdo->exec("CREATE TABLE IF NOT EXISTS compras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha_compra DATETIME NOT NULL,
        proveedor VARCHAR(255),
        tipo_compra ENUM('ingredientes', 'insumos', 'equipamiento', 'otros') NOT NULL DEFAULT 'ingredientes',
        monto_total DECIMAL(12,2) NOT NULL,
        metodo_pago ENUM('efectivo', 'transferencia', 'tarjeta', 'credito') NOT NULL DEFAULT 'efectivo',
        estado ENUM('pendiente', 'pagado', 'cancelado') DEFAULT 'pagado',
        notas TEXT,
        usuario VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha_compra),
        INDEX idx_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla de detalle de compras
    $pdo->exec("CREATE TABLE IF NOT EXISTS compras_detalle (
        id INT AUTO_INCREMENT PRIMARY KEY,
        compra_id INT NOT NULL,
        ingrediente_id INT,
        nombre_item VARCHAR(255) NOT NULL,
        cantidad DECIMAL(10,2) NOT NULL,
        unidad VARCHAR(50),
        precio_unitario DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
        FOREIGN KEY (ingrediente_id) REFERENCES ingredients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla de capital de trabajo
    $pdo->exec("CREATE TABLE IF NOT EXISTS capital_trabajo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
        ingresos_ventas DECIMAL(12,2) NOT NULL DEFAULT 0,
        egresos_compras DECIMAL(12,2) NOT NULL DEFAULT 0,
        egresos_gastos DECIMAL(12,2) NOT NULL DEFAULT 0,
        saldo_final DECIMAL(12,2) NOT NULL DEFAULT 0,
        notas TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo json_encode([
        'success' => true,
        'message' => 'Tablas de compras creadas correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
