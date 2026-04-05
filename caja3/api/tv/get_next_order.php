<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php',
    __DIR__ . '/../../../../../../config.php'
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require $path; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Tomar el primer pedido pendiente (orden de llegada)
    $stmt = $pdo->query("SELECT * FROM tv_orders WHERE status = 'pendiente' ORDER BY created_at ASC LIMIT 1");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'No hay pedidos pendientes']);
        exit;
    }

    // Marcar como en_proceso para que no lo tome otra cajera
    $pdo->prepare("UPDATE tv_orders SET status = 'en_proceso' WHERE id = ?")->execute([$order['id']]);

    // Obtener items
    $stmtItems = $pdo->prepare("SELECT * FROM tv_order_items WHERE order_id = ?");
    $stmtItems->execute([$order['id']]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Formatear items igual que el carrito de MenuApp
    $cartItems = array_map(function($item) {
        $extra = $item['customizations'] ? json_decode($item['customizations'], true) : [];
        return [
            'id'            => $item['product_id'],
            'name'          => $item['product_name'],
            'price'         => (float)$item['price'],
            'quantity'      => 1,
            'selections'    => $extra['selections'] ?? null,
            'fixed_items'   => $extra['fixed_items'] ?? null,
            'category_name' => $extra['category_name'] ?? null,
            'type'          => $extra['type'] ?? null,
            'displayName'   => $extra['displayName'] ?? null,
            'tv_order_item_id' => $item['id']
        ];
    }, $items);

    echo json_encode([
        'success'    => true,
        'tv_order_id' => $order['id'],
        'total'      => (float)$order['total'],
        'created_at' => $order['created_at'],
        'items'      => $cartItems
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
