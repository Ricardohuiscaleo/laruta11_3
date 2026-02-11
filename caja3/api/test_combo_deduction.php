<?php
header('Content-Type: application/json');

function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../'];
    foreach ($levels as $level) {
        if (file_exists(__DIR__ . '/' . $level . 'config.php')) 
            return __DIR__ . '/' . $level . 'config.php';
    }
    return null;
}

$config = include findConfig();
$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Simular combo_data del ejemplo
$combo_data = [
    'fixed_items' => [
        ['product_id' => 9, 'product_name' => 'Hamburguesa Clásica', 'quantity' => 1],
        ['product_id' => 6, 'product_name' => 'Ave Italiana', 'quantity' => 1],
        ['product_id' => 17, 'product_name' => 'Papas Fritas Individual', 'quantity' => 1]
    ],
    'selections' => [
        'Bebidas' => [
            ['id' => 99, 'name' => 'Coca-Cola Lata 350ml', 'price' => '0.00'],
            ['id' => 99, 'name' => 'Coca-Cola Lata 350ml', 'price' => '0.00']
        ]
    ]
];

$report = [
    'combo_name' => 'Combo Dupla',
    'products_to_deduct' => []
];

// Analizar fixed_items
foreach ($combo_data['fixed_items'] as $fixed) {
    $product_id = $fixed['product_id'];
    $quantity = $fixed['quantity'];
    
    // Verificar si tiene receta
    $stmt = $pdo->prepare("
        SELECT pr.ingredient_id, i.name as ingredient_name, pr.quantity, pr.unit
        FROM product_recipes pr
        JOIN ingredients i ON pr.ingredient_id = i.id
        WHERE pr.product_id = ? AND i.is_active = 1
    ");
    $stmt->execute([$product_id]);
    $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $report['products_to_deduct'][] = [
        'product_id' => $product_id,
        'product_name' => $fixed['product_name'],
        'quantity' => $quantity,
        'has_recipe' => !empty($recipe),
        'ingredients' => array_map(function($ing) use ($quantity) {
            $deduct = $ing['quantity'] * $quantity;
            if ($ing['unit'] === 'g') {
                $deduct = $deduct / 1000;
            }
            return [
                'id' => $ing['ingredient_id'],
                'name' => $ing['ingredient_name'],
                'quantity' => $ing['quantity'],
                'unit' => $ing['unit'],
                'to_deduct' => $deduct . ($ing['unit'] === 'g' ? ' kg' : ' ' . $ing['unit'])
            ];
        }, $recipe)
    ];
}

// Analizar selections
foreach ($combo_data['selections'] as $group => $selection) {
    if (is_array($selection) && isset($selection[0])) {
        foreach ($selection as $sel) {
            $sel_id = $sel['id'] ?? null;
            if ($sel_id) {
                // Verificar si tiene receta
                $stmt = $pdo->prepare("
                    SELECT pr.ingredient_id, i.name as ingredient_name, pr.quantity, pr.unit
                    FROM product_recipes pr
                    JOIN ingredients i ON pr.ingredient_id = i.id
                    WHERE pr.product_id = ? AND i.is_active = 1
                ");
                $stmt->execute([$sel_id]);
                $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $report['products_to_deduct'][] = [
                    'product_id' => $sel_id,
                    'product_name' => $sel['name'],
                    'quantity' => 1,
                    'group' => $group,
                    'has_recipe' => !empty($recipe),
                    'ingredients' => array_map(function($ing) {
                        $deduct = $ing['quantity'];
                        if ($ing['unit'] === 'g') {
                            $deduct = $deduct / 1000;
                        }
                        return [
                            'id' => $ing['ingredient_id'],
                            'name' => $ing['ingredient_name'],
                            'quantity' => $ing['quantity'],
                            'unit' => $ing['unit'],
                            'to_deduct' => $deduct . ($ing['unit'] === 'g' ? ' kg' : ' ' . $ing['unit'])
                        ];
                    }, $recipe)
                ];
            }
        }
    }
}

$report['summary'] = [
    'total_products' => count($report['products_to_deduct']),
    'products_with_recipe' => count(array_filter($report['products_to_deduct'], fn($p) => $p['has_recipe'])),
    'products_without_recipe' => count(array_filter($report['products_to_deduct'], fn($p) => !$p['has_recipe']))
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
