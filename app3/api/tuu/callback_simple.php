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
            SELECT product_id, product_name, quantity, item_type, combo_data
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
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'cantidad' => $item['quantity'],
                    'is_combo' => true,
                    'combo_id' => $combo_data['combo_id'] ?? null,
                    'fixed_items' => $combo_data['fixed_items'] ?? [],
                    'selections' => $combo_data['selections'] ?? []
                ];
            } else {
                // Producto normal
                $inventory_item = [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'cantidad' => $item['quantity']
                ];
                
                // Agregar personalizaciones si existen
                if ($item['combo_data']) {
                    $combo_data = json_decode($item['combo_data'], true);
                    if (isset($combo_data['customizations'])) {
                        $inventory_item['customizations'] = $combo_data['customizations'];
                    }
                }
                
                $inventory_items[] = $inventory_item;
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
        
        // Obtener datos completos de la orden para WhatsApp
        $order_stmt = $pdo->prepare("SELECT * FROM tuu_orders WHERE order_number = ?");
        $order_stmt->execute([$order_reference]);
        $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Construir mensaje de WhatsApp
            $whatsapp_message = "*PEDIDO CONFIRMADO - LA RUTA 11*\n\n";
            $whatsapp_message .= "*Pedido:* {$order_reference}\n";
            $whatsapp_message .= "*Cliente:* {$order['customer_name']}\n";
            $whatsapp_message .= "*Estado:* Pagado con Webpay\n";
            $whatsapp_message .= "*Total:* $" . number_format($order['product_price'], 0, ',', '.') . "\n";
            $whatsapp_message .= "*Método:* Webpay\n\n";
            
            // Agregar productos
            if (!empty($order_items)) {
                $whatsapp_message .= "*PRODUCTOS:*\n";
                $index = 1;
                foreach ($order_items as $item) {
                    $whatsapp_message .= "{$index}. {$item['product_name']} x{$item['quantity']} - $" . number_format($item['product_price'] * $item['quantity'], 0, ',', '.') . "\n";
                    
                    // Agregar personalizaciones si existen
                    if ($item['combo_data']) {
                        $combo_data = json_decode($item['combo_data'], true);
                        
                        // Combos
                        if (isset($combo_data['fixed_items']) || isset($combo_data['selections'])) {
                            $include_items = [];
                            
                            if (isset($combo_data['fixed_items'])) {
                                foreach ($combo_data['fixed_items'] as $fixed) {
                                    $include_items[] = "{$item['quantity']}x " . ($fixed['product_name'] ?? $fixed['name']);
                                }
                            }
                            
                            if (isset($combo_data['selections'])) {
                                foreach ($combo_data['selections'] as $selection) {
                                    if (is_array($selection)) {
                                        foreach ($selection as $sel) {
                                            $include_items[] = "{$item['quantity']}x {$sel['name']}";
                                        }
                                    } else {
                                        $include_items[] = "{$item['quantity']}x {$selection['name']}";
                                    }
                                }
                            }
                            
                            if (!empty($include_items)) {
                                $whatsapp_message .= "   Incluye: " . implode(', ', $include_items) . "\n";
                            }
                        }
                        
                        // Personalizaciones
                        if (isset($combo_data['customizations'])) {
                            $custom_items = [];
                            foreach ($combo_data['customizations'] as $custom) {
                                $custom_items[] = "{$custom['quantity']}x {$custom['name']}";
                            }
                            if (!empty($custom_items)) {
                                $whatsapp_message .= "   Incluye: " . implode(', ', $custom_items) . "\n";
                            }
                        }
                    }
                    
                    $index++;
                }
                $whatsapp_message .= "\n";
            }
            
            // Obtener info de delivery
            $delivery_stmt = $pdo->prepare("SELECT * FROM tuu_delivery_info WHERE order_number = ?");
            $delivery_stmt->execute([$order_reference]);
            $delivery_info = $delivery_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($delivery_info) {
                $whatsapp_message .= "*TIPO DE ENTREGA:* " . ($delivery_info['delivery_type'] === 'delivery' ? '🚴 Delivery' : '🏪 Retiro en local') . "\n";
                
                if ($delivery_info['delivery_type'] === 'delivery' && $delivery_info['delivery_address']) {
                    $whatsapp_message .= "*DIRECCIÓN:* {$delivery_info['delivery_address']}\n";
                }
                
                if ($delivery_info['delivery_type'] === 'pickup' && $delivery_info['pickup_time']) {
                    $whatsapp_message .= "*HORARIO RETIRO:* {$delivery_info['pickup_time']}\n";
                }
                
                if ($delivery_info['customer_notes']) {
                    $whatsapp_message .= "\n*NOTAS DEL CLIENTE:*\n{$delivery_info['customer_notes']}\n";
                }
            }
            
            if ($order['delivery_fee'] > 0) {
                $whatsapp_message .= "*COSTO DELIVERY:* $" . number_format($order['delivery_fee'], 0, ',', '.') . "\n";
            }
            
            $whatsapp_message .= "\n*Pago confirmado con Webpay*\n\n";
            $whatsapp_message .= "Pedido realizado desde la app web.";
            
            // Enviar a WhatsApp
            $whatsapp_url = "https://api.whatsapp.com/send?phone=56936227422&text=" . urlencode($whatsapp_message);
            
            // Usar cURL para enviar (opcional, solo para log)
            error_log("Mensaje WhatsApp generado para orden: $order_reference");
            error_log("URL WhatsApp: $whatsapp_url");
        }
        
    } catch (Exception $e) {
        error_log("Error procesando callback: " . $e->getMessage());
    }
}

// Responder OK a TUU
http_response_code(200);
echo "OK";
?>