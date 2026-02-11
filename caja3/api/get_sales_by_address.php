<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php',
    __DIR__ . '/../../../../../../config.php',
    __DIR__ . '/../../../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener ventas del mes actual con lógica de turnos (17:30 a 04:00)
    $query = "
        SELECT 
            COALESCE(NULLIF(TRIM(delivery_address), ''), 'Retiro en Local') as address,
            delivery_type,
            created_at,
            product_price,
            COALESCE(delivery_fee, 0) as delivery_fee
        FROM tuu_orders
        WHERE payment_status = 'paid'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrar por mes actual aplicando lógica de turnos
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentMonth = (int)$now->format('m');
    $currentYear = (int)$now->format('Y');
    
    $results = [];
    foreach ($allOrders as $order) {
        $date = new DateTime($order['created_at'], new DateTimeZone('America/Santiago'));
        $hour = (int)$date->format('G');
        $minute = (int)$date->format('i');
        
        $shiftDate = clone $date;
        // Turno: 17:30 a 04:00 del día siguiente
        if ($hour >= 0 && $hour < 4) {
            $shiftDate->modify('-1 day');
        } elseif ($hour < 17 || ($hour == 17 && $minute < 30)) {
            $shiftDate->modify('-1 day');
        }
        
        // Verificar que el shift day esté en el mes correcto
        if ($shiftDate->format('Y-m') === "$currentYear-" . str_pad($currentMonth, 2, '0', STR_PAD_LEFT)) {
            $results[] = $order;
        }
    }
    
    // Agrupar direcciones similares
    $grouped = [];
    
    foreach ($results as $row) {
        $address = $row['address'];
        $deliveryType = $row['delivery_type'];
        $productPrice = floatval($row['product_price']);
        $deliveryFee = floatval($row['delivery_fee']);
        
        // Normalizar dirección
        $normalizedAddress = normalizeAddress($address, $deliveryType);
        
        if (!isset($grouped[$normalizedAddress])) {
            $grouped[$normalizedAddress] = [
                'address' => $normalizedAddress,
                'order_count' => 0,
                'total_sales' => 0,
                'total_delivery' => 0,
                'delivery_type' => $deliveryType
            ];
        }
        
        $grouped[$normalizedAddress]['order_count']++;
        $grouped[$normalizedAddress]['total_sales'] += $productPrice;
        $grouped[$normalizedAddress]['total_delivery'] += $deliveryFee;
    }
    
    // Ordenar por ventas y tomar top 10
    usort($grouped, function($a, $b) {
        return $b['total_sales'] - $a['total_sales'];
    });
    
    $top10 = array_slice($grouped, 0, 10);
    
    echo json_encode([
        'success' => true,
        'data' => $top10
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function normalizeAddress($address, $deliveryType) {
    $address = trim($address);
    
    // Detectar casos especiales que son delivery aunque estén marcados como pickup
    $isSpecialDelivery = stripos($address, 'lluta') !== false || 
                         stripos($address, 'elecciones') !== false ||
                         stripos($address, 'eleccion') !== false;
    
    // Si es pickup Y NO es caso especial, retornar "Retiro en Local"
    if ($deliveryType === 'pickup' && !$isSpecialDelivery) {
        return 'Retiro en Local';
    }
    
    // Si está vacío, es retiro en local
    if (empty($address)) {
        return 'Retiro en Local';
    }
    
    // Normalizar dirección
    $normalized = strtoupper(trim($address));
    
    // Remover números de departamento/casa
    $normalized = preg_replace('/\s+(DEPTO|DPTO|CASA|#)\s*[A-Z0-9\-]+/i', '', $normalized);
    
    // Extraer calle principal
    $parts = preg_split('/[,\s]+/', $normalized);
    if (count($parts) >= 2) {
        return ucwords(strtolower($parts[0] . ' ' . $parts[1]));
    }
    
    return ucwords(strtolower($normalized));
}
