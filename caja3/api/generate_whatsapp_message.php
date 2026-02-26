<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order'])) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

$order = $input['order'];
$payment_method = $input['payment_method'] ?? 'Efectivo';
$status = $input['status'] ?? 'Confirmado';

$message = "> 🍔 *PEDIDO - LA RUTA 11*\n\n";
$message .= "*📋 Datos del pedido:*\n";
$message .= "- *Pedido:* {$order['order_id']}\n";
$message .= "- *Cliente:* {$order['customer_name']}\n";
$message .= "- *Estado:* {$status}\n";
$message .= "- *Total:* $" . number_format($order['total'], 0, ',', '.') . "\n";
$message .= "- *Método:* {$payment_method}\n\n";

$message .= "*📦 Productos:*\n";

foreach ($order['items'] as $idx => $item) {
    $num = $idx + 1;
    $itemTotal = $item['price'] * $item['quantity'];

    if (!empty($item['customizations'])) {
        foreach ($item['customizations'] as $custom) {
            $itemTotal += $custom['price'] * $custom['quantity'];
        }
    }

    $message .= "{$num}. {$item['name']} x{$item['quantity']} - $" . number_format($itemTotal, 0, ',', '.') . "\n";

    $allIncludes = [];

    // Agregar customizations
    if (!empty($item['customizations'])) {
        foreach ($item['customizations'] as $c) {
            $allIncludes[] = "{$c['quantity']}x {$c['name']}";
        }
    }

    // Agregar fixed_items del combo
    if (!empty($item['fixed_items'])) {
        foreach ($item['fixed_items'] as $f) {
            $allIncludes[] = is_string($f) ? $f : ($f['product_name'] ?? $f['name'] ?? '');
        }
    }

    // Agregar selections del combo
    if (!empty($item['selections']) && is_array($item['selections'])) {
        foreach ($item['selections'] as $categoryItems) {
            if (is_array($categoryItems)) {
                foreach ($categoryItems as $s) {
                    if (is_array($s)) {
                        $allIncludes[] = $s['name'] ?? $s['product_name'] ?? '';
                    }
                }
            }
            elseif (is_array($categoryItems)) {
                $allIncludes[] = $categoryItems['name'] ?? $categoryItems['product_name'] ?? '';
            }
        }
    }

    if (!empty($allIncludes)) {
        foreach (array_filter($allIncludes) as $inc) {
            $message .= "   - {$inc}\n";
        }
    }
}

$message .= "\n*🚚 Entrega:*\n";
if ($order['delivery_type'] === 'delivery') {
    $message .= "- *Tipo:* 🚴 Delivery\n";
    if (!empty($order['delivery_address'])) {
        $message .= "- *Dirección:* {$order['delivery_address']}\n";
    }
    if ($order['delivery_fee'] > 0) {
        $message .= "- *Costo delivery:* $" . number_format($order['delivery_fee'], 0, ',', '.') . "\n";
    }
}
else {
    $message .= "- *Tipo:* 🏪 Retiro en local\n";
}

if (!empty($order['customer_notes'])) {
    $message .= "\n*📝 Notas del cliente:*\n> {$order['customer_notes']}\n";
}

$message .= "\n> *💰 TOTAL: $" . number_format($order['total'], 0, ',', '.') . "*\n\n";
$message .= "_Pedido realizado desde la caja._";

$whatsappUrl = "https://wa.me/56936227422?text=" . urlencode($message);

echo json_encode([
    'success' => true,
    'message' => $message,
    'whatsapp_url' => $whatsappUrl
]);
?>