<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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
    $attempted = implode(', ', array_map(function($p) { return file_exists($p) ? "$p (exists)" : "$p (not found)"; }, $config_paths));
    echo json_encode(['success' => false, 'error' => 'Config no encontrado', 'attempted' => $attempted]);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $ingrediente_id = $_GET['ingrediente_id'] ?? null;

    if (!$ingrediente_id) {
        echo json_encode(['success' => false, 'error' => 'ingrediente_id requerido']);
        exit;
    }

    // Obtener el precio más reciente de este ingrediente
    $stmt = $pdo->prepare("
        SELECT 
            cd.precio_unitario,
            cd.cantidad,
            cd.unidad,
            cd.subtotal,
            c.fecha_compra,
            c.proveedor
        FROM compras_detalle cd
        JOIN compras c ON cd.compra_id = c.id
        WHERE cd.ingrediente_id = ?
        ORDER BY c.fecha_compra DESC
        LIMIT 1
    ");
    
    $stmt->execute([$ingrediente_id]);
    $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ultimo) {
        echo json_encode([
            'success' => true,
            'precio_unitario' => $ultimo['precio_unitario'],
            'unidad' => $ultimo['unidad'],
            'ultima_cantidad' => $ultimo['cantidad'],
            'ultimo_subtotal' => $ultimo['subtotal'],
            'fecha_compra' => $ultimo['fecha_compra'],
            'proveedor' => $ultimo['proveedor']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Sin historial de compras'
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
