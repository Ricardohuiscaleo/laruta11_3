<?php
/**
 * Register packaging consumption (bags) for an order.
 * Called from MiniComandas when dispatching/delivering.
 *
 * POST /api/register_packaging_consumption.php
 * Body: { order_number: string, bolsa_grande: int, bolsa_mediana: int }
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $config = require __DIR__ . '/../config.php';
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $input = json_decode(file_get_contents('php://input'), true);

    $orderNumber  = trim($input['order_number'] ?? '');
    $bolsaGrande  = max(0, min(10, (int) ($input['bolsa_grande'] ?? 0)));
    $bolsaMediana = max(0, min(10, (int) ($input['bolsa_mediana'] ?? 0)));

    if ($orderNumber === '') {
        echo json_encode(['success' => false, 'error' => 'order_number es requerido']);
        exit;
    }

    if ($bolsaGrande === 0 && $bolsaMediana === 0) {
        echo json_encode(['success' => true, 'transactions' => [], 'warnings' => []]);
        exit;
    }

    // Packaging bag definitions
    $bags = [
        'bolsa_grande'  => ['id' => 130, 'name' => 'BOLSA PAPEL CAFE CON MANILLA 36x20x39 CM', 'qty' => $bolsaGrande],
        'bolsa_mediana' => ['id' => 167, 'name' => 'BOLSA PAPEL CAFE CON MANILLA 30x12x32 CM', 'qty' => $bolsaMediana],
    ];

    // Idempotency guard
    $checkStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM inventory_transactions 
         WHERE order_reference = ? AND transaction_type = 'consumption' AND ingredient_id IN (130, 167)"
    );
    $checkStmt->execute([$orderNumber]);
    if ((int) $checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => "Ya existen transacciones de packaging para el pedido {$orderNumber}"]);
        exit;
    }

    $transactions = [];
    $warnings = [];

    $pdo->beginTransaction();

    foreach ($bags as $bag) {
        if ($bag['qty'] <= 0) continue;

        $stockStmt = $pdo->prepare("SELECT current_stock FROM ingredients WHERE id = ?");
        $stockStmt->execute([$bag['id']]);
        $currentStock = (float) $stockStmt->fetchColumn();
        $newStock = $currentStock - $bag['qty'];

        if ($currentStock < $bag['qty']) {
            $warnings[] = "Stock insuficiente para {$bag['name']}: stock actual {$currentStock}, consumo {$bag['qty']}";
        }

        $insertStmt = $pdo->prepare(
            "INSERT INTO inventory_transactions 
             (transaction_type, ingredient_id, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id, notes, created_at)
             VALUES ('consumption', ?, NULL, ?, 'unidad', ?, ?, ?, NULL, ?, NOW())"
        );
        $insertStmt->execute([
            $bag['id'],
            -$bag['qty'],
            $currentStock,
            $newStock,
            $orderNumber,
            "Consumo packaging: {$bag['name']} x{$bag['qty']} - Pedido {$orderNumber}"
        ]);

        $updateStmt = $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newStock, $bag['id']]);

        $transactions[] = [
            'ingredient_id'   => $bag['id'],
            'ingredient_name' => $bag['name'],
            'quantity'         => -$bag['qty'],
            'previous_stock'   => $currentStock,
            'new_stock'        => $newStock,
        ];
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'transactions' => $transactions, 'warnings' => $warnings]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}
