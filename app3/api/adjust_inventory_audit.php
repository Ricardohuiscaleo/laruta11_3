<?php
/**
 * Script de Ajuste de Inventario - Auditoría Montina Big
 * Ejecutar UNA SOLA VEZ para corregir discrepancia identificada
 */

header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
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
    
    $pdo->beginTransaction();
    
    // 1. Obtener stock actual
    $stmt = $pdo->prepare("SELECT current_stock FROM ingredients WHERE id = 45");
    $stmt->execute();
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    $previous_stock = $current['current_stock'];
    
    // 2. Actualizar a stock físico verificado
    $new_stock = 7.00;
    $adjustment = $new_stock - $previous_stock;
    
    $update_stmt = $pdo->prepare("
        UPDATE ingredients 
        SET current_stock = ?,
            updated_at = NOW()
        WHERE id = 45
    ");
    $update_stmt->execute([$new_stock]);
    
    // 3. Registrar transacción de ajuste
    $notes = "Ajuste por auditoría de inventario. 4 órdenes R11- (sistema legacy webpay) sin procesar inventario:\n" .
             "1) R11-1762129650-5521 (03-nov, carolina, 2 Montinas)\n" .
             "2) R11-1762302053-1306 (05-nov, jeremy.vilman, 2 Montinas)\n" .
             "3) R11-1763503342-6455 (18-nov, Roberto Giovanni, 2 Montinas)\n" .
             "4) R11-1763595639-2012 (19-nov, Roberto Giovanni, 4 Montinas)\n" .
             "Total: 10 Montinas sin descontar. Stock físico verificado: 7 unidades. Diferencia +1u favorable (posible compra informal).";
    
    $trans_stmt = $pdo->prepare("
        INSERT INTO inventory_transactions 
        (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, notes, created_by)
        VALUES ('adjustment', 45, ?, 'unidad', ?, ?, ?, 'Admin')
    ");
    $trans_stmt->execute([$adjustment, $previous_stock, $new_stock, $notes]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ajuste de inventario completado',
        'previous_stock' => $previous_stock,
        'new_stock' => $new_stock,
        'adjustment' => $adjustment,
        'transaction_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
