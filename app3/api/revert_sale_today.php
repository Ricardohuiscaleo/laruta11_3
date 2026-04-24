<?php
/**
 * Revierte el descuento temporal aplicado por apply_sale_today.php.
 * Quita is_featured y sale_price de los 4 productos.
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

    $productNames = [
        'Completo Italiano',
        'Hass de Filete Pollo',
        'Completo Tocino Ahumado',
        'Hass de Carne'
    ];

    $placeholders = implode(',', array_fill(0, count($productNames), '?'));

    // Verificar estado actual
    $stmt = $pdo->prepare("SELECT id, name, price, sale_price, is_featured FROM products WHERE name IN ($placeholders) AND is_active = 1");
    $stmt->execute($productNames);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revertir: quitar is_featured y sale_price
    $updateStmt = $pdo->prepare("UPDATE products SET is_featured = 0, sale_price = NULL WHERE name IN ($placeholders) AND is_active = 1");
    $updateStmt->execute($productNames);
    $affected = $updateStmt->rowCount();

    $results = array_map(function($p) {
        return [
            'id' => $p['id'],
            'name' => $p['name'],
            'price' => (int)$p['price'],
            'was_sale_price' => $p['sale_price'],
            'was_featured' => (int)$p['is_featured']
        ];
    }, $products);

    echo json_encode([
        'success' => true,
        'message' => "Descuento revertido en $affected productos",
        'products' => $results
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
