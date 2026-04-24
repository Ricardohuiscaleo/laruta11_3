<?php
/**
 * Aplica descuento 10% temporal (solo hoy) a productos seleccionados.
 * Activa is_featured + sale_price para mostrar badge "🔥 OFERTA" en app3/caja3.
 * 
 * Productos: Completo Italiano, Hass de Filete Pollo, Completo Tocino Ahumado, Hass de Carne
 * NO incluye: Hot-Dog
 * 
 * Para revertir: ejecutar revert_sale_today.php
 */
$config = require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Productos a los que aplicar 10% descuento
    $productNames = [
        'Completo Italiano',
        'Hass de Filete Pollo',
        'Completo Tocino Ahumado',
        'Hass de Carne'
    ];

    $placeholders = implode(',', array_fill(0, count($productNames), '?'));
    
    // Primero verificar los productos
    $stmt = $pdo->prepare("SELECT id, name, price, sale_price, is_featured FROM products WHERE name IN ($placeholders) AND is_active = 1");
    $stmt->execute($productNames);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($products) === 0) {
        echo json_encode(['success' => false, 'error' => 'No se encontraron productos']);
        exit;
    }

    $results = [];
    $updateStmt = $pdo->prepare("UPDATE products SET is_featured = 1, sale_price = ? WHERE id = ?");

    foreach ($products as $product) {
        $salePrice = floor($product['price'] * 0.9 / 10) * 10; // 10% descuento, redondeado a decena
        $updateStmt->execute([$salePrice, $product['id']]);
        $results[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => (int)$product['price'],
            'sale_price' => $salePrice,
            'descuento' => (int)$product['price'] - $salePrice,
            'prev_is_featured' => (int)$product['is_featured'],
            'prev_sale_price' => $product['sale_price']
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Descuento 10% aplicado a ' . count($results) . ' productos',
        'nota' => 'TEMPORAL - revertir con revert_sale_today.php',
        'products' => $results
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
