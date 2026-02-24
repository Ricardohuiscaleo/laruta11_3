<?php
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

error_log("=== TUU CALLBACK RECIBIDO ===");
error_log("GET: " . json_encode($_GET));
error_log("POST: " . json_encode($_POST));

// Procesar pago exitoso
if (isset($_GET['x_reference']) && isset($_GET['x_result']) && $_GET['x_result'] === 'completed') {
    $order_reference = $_GET['x_reference'];

    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

        // Actualizar estado de la orden
        $update_stmt = $pdo->prepare("UPDATE tuu_orders SET status = 'completed' WHERE order_number = ?");
        $update_stmt->execute([$order_reference]);

        // Obtener items de la orden para procesar inventario
        $items_stmt = $pdo->prepare("
            SELECT id, product_id, product_name, quantity, item_type, combo_data
            FROM tuu_order_items 
            WHERE order_reference = ?
        ");
        $items_stmt->execute([$order_reference]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Preparar datos para API de inventario
        $inventory_items = [];
        foreach ($order_items as $item) {
            if ($item['item_type'] === 'combo' && $item['combo_data']) {
                // Es un combo
                $combo_data = json_decode($item['combo_data'], true);
                $inventory_items[] = [
                    'order_item_id' => $item['id'],
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'cantidad' => $item['quantity'],
                    'is_combo' => true,
                    'combo_id' => $combo_data['combo_id'] ?? null,
                    'fixed_items' => $combo_data['fixed_items'] ?? [],
                    'selections' => $combo_data['selections'] ?? []
                ];
            }
            else {
                // Producto normal
                $inventory_items[] = [
                    'order_item_id' => $item['id'],
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'cantidad' => $item['quantity']
                ];
            }
        }

        // Llamar API de inventario
        if (!empty($inventory_items)) {
            $inventory_data = json_encode(['items' => $inventory_items]);

            $ch = curl_init('https://app.laruta11.cl/api/process_sale_inventory.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $inventory_data);

            $inventory_response = curl_exec($ch);
            curl_close($ch);

            error_log("Inventario procesado para orden: $order_reference");
            error_log("Respuesta inventario: $inventory_response");
        }

    }
    catch (Exception $e) {
        error_log("Error procesando callback: " . $e->getMessage());
    }
}

// Responder OK a TUU
http_response_code(200);
echo "OK";
?>