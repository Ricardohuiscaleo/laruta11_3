<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
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

    $input = json_decode(file_get_contents('php://input'), true);
    $compra_id = $input['compra_id'] ?? null;

    if (!$compra_id) {
        throw new Exception('ID de compra requerido');
    }

    $pdo->beginTransaction();

    // Obtener items para revertir inventario
    $stmt = $pdo->prepare("SELECT cd.*, c.tipo_compra 
        FROM compras_detalle cd 
        JOIN compras c ON cd.compra_id = c.id 
        WHERE cd.compra_id = ?");
    $stmt->execute([$compra_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revertir inventario
    foreach ($items as $item) {
        $item_type = $item['item_type'] ?? 'ingredient';
        
        if ($item_type === 'ingredient' && $item['ingrediente_id']) {
            $updateStmt = $pdo->prepare("UPDATE ingredients 
                SET current_stock = current_stock - ? 
                WHERE id = ?");
            $updateStmt->execute([$item['cantidad'], $item['ingrediente_id']]);
        } elseif ($item_type === 'product' && $item['product_id']) {
            $updateStmt = $pdo->prepare("UPDATE products 
                SET stock_quantity = stock_quantity - ? 
                WHERE id = ?");
            $updateStmt->execute([$item['cantidad'], $item['product_id']]);
        }
    }

    // Eliminar items
    $deleteItems = $pdo->prepare("DELETE FROM compras_detalle WHERE compra_id = ?");
    $deleteItems->execute([$compra_id]);

    // Eliminar compra
    $deleteCompra = $pdo->prepare("DELETE FROM compras WHERE id = ?");
    $deleteCompra->execute([$compra_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Compra eliminada correctamente'
    ]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
