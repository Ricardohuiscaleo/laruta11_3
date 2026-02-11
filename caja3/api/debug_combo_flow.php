<?php
header('Content-Type: text/html; charset=utf-8');

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

echo "<h1>🔍 Debug: Flujo de Combos</h1>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5} pre{background:#fff;padding:15px;border-radius:5px;overflow:auto} table{border-collapse:collapse;margin:20px 0;background:#fff} td,th{border:1px solid #ddd;padding:8px} h2{color:#f97316;margin-top:30px}</style>";

// 1. ESTRUCTURA: Tabla combos
echo "<h2>📦 1. TABLA COMBOS (Sistema Nuevo)</h2>";
$stmt = $pdo->query("SELECT id, name, price, active FROM combos WHERE active = 1 LIMIT 5");
$combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>ID</th><th>Nombre</th><th>Precio</th></tr>";
foreach ($combos as $c) {
    echo "<tr><td>{$c['id']}</td><td>{$c['name']}</td><td>\${$c['price']}</td></tr>";
}
echo "</table>";

// 2. ESTRUCTURA: combo_items (qué incluye cada combo)
echo "<h2>🍔 2. COMBO_ITEMS (Productos fijos del combo)</h2>";
$stmt = $pdo->query("
    SELECT ci.combo_id, c.name as combo_name, ci.product_id, p.name as product_name, 
           p.category_id, cat.name as category_name, ci.quantity
    FROM combo_items ci
    JOIN combos c ON ci.combo_id = c.id
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE c.active = 1
    LIMIT 10
");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>Combo</th><th>Product ID</th><th>Producto</th><th>Categoría</th><th>Qty</th></tr>";
foreach ($items as $i) {
    $warning = $i['category_id'] == 8 ? ' ⚠️ ES COMBO' : '';
    echo "<tr><td>{$i['combo_name']}</td><td>{$i['product_id']}</td><td>{$i['product_name']}{$warning}</td><td>{$i['category_name']}</td><td>{$i['quantity']}</td></tr>";
}
echo "</table>";

// 3. ESTRUCTURA: combo_selections (bebidas seleccionables)
echo "<h2>🥤 3. COMBO_SELECTIONS (Opciones seleccionables)</h2>";
$stmt = $pdo->query("
    SELECT cs.combo_id, c.name as combo_name, cs.selection_group, 
           cs.product_id, p.name as product_name, p.category_id
    FROM combo_selections cs
    JOIN combos c ON cs.combo_id = c.id
    JOIN products p ON cs.product_id = p.id
    WHERE c.active = 1
    LIMIT 10
");
$selections = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>Combo</th><th>Grupo</th><th>Product ID</th><th>Opción</th><th>Cat ID</th></tr>";
foreach ($selections as $s) {
    echo "<tr><td>{$s['combo_name']}</td><td>{$s['selection_group']}</td><td>{$s['product_id']}</td><td>{$s['product_name']}</td><td>{$s['category_id']}</td></tr>";
}
echo "</table>";

// 4. PRODUCTOS: ¿Hay combos en products?
echo "<h2>⚠️ 4. PRODUCTS con category_id=8 (Combos duplicados)</h2>";
$stmt = $pdo->query("SELECT id, name, price, is_active, category_id FROM products WHERE category_id = 8");
$product_combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($product_combos) > 0) {
    echo "<p style='color:red'>⚠️ PROBLEMA: Hay " . count($product_combos) . " combos en tabla products</p>";
    echo "<table><tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Activo</th></tr>";
    foreach ($product_combos as $p) {
        $status = $p['is_active'] ? '✓ Activo' : '✗ Inactivo';
        echo "<tr><td>{$p['id']}</td><td>{$p['name']}</td><td>\${$p['price']}</td><td>{$status}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:green'>✓ OK: No hay combos en tabla products</p>";
}

// 5. ÓRDENES: Ver ejemplo de combo_data guardado
echo "<h2>📝 5. EJEMPLO DE COMBO EN ORDEN (tuu_order_items)</h2>";
$stmt = $pdo->query("
    SELECT id, order_reference, product_name, item_type, combo_data, quantity
    FROM tuu_order_items 
    WHERE item_type = 'combo' 
    ORDER BY id DESC 
    LIMIT 3
");
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($order_items) > 0) {
    foreach ($order_items as $oi) {
        echo "<h3>Orden: {$oi['order_reference']} - {$oi['product_name']}</h3>";
        echo "<pre>" . json_encode(json_decode($oi['combo_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
} else {
    echo "<p>No hay órdenes con combos aún</p>";
}

// 6. ANÁLISIS: ¿Qué pasa al vender?
echo "<h2>🎯 6. ANÁLISIS DEL FLUJO</h2>";
echo "<div style='background:#fff;padding:20px;border-radius:5px'>";
echo "<h3>Flujo Actual:</h3>";
echo "<ol>";
echo "<li><strong>Usuario selecciona combo</strong> → Abre ComboModal</li>";
echo "<li><strong>ComboModal carga datos</strong> → GET /api/get_combos.php?combo_id=X</li>";
echo "<li><strong>Usuario elige bebida</strong> → Selecciona de combo_selections</li>";
echo "<li><strong>Se agrega al carrito</strong> → Guarda: fixed_items + selections</li>";
echo "<li><strong>Al pagar</strong> → Guarda en tuu_order_items con combo_data JSON</li>";
echo "<li><strong>Descuento inventario</strong> → processInventoryDeduction()</li>";
echo "</ol>";

echo "<h3 style='color:#f97316'>❓ PREGUNTA CLAVE:</h3>";
echo "<p><strong>¿Qué descuenta processInventoryDeduction() cuando es combo?</strong></p>";
echo "<p>Actualmente en <code>create_order.php</code> línea 120-130:</p>";
echo "<pre>if (\$payment_status === 'paid') {
    processInventoryDeduction(\$pdo, \$cart_items);
}</pre>";
echo "<p>La función <code>processInventoryDeduction()</code> recibe <code>\$cart_items</code> que incluye:</p>";
echo "<ul>";
echo "<li><code>id</code>: ID del combo (de tabla combos)</li>";
echo "<li><code>type</code>: 'combo'</li>";
echo "<li><code>fixed_items</code>: Array con product_id de productos fijos</li>";
echo "<li><code>selections</code>: Array con product_id de bebidas seleccionadas</li>";
echo "</ul>";

echo "<h3 style='color:red'>⚠️ PROBLEMA DETECTADO:</h3>";
echo "<p><code>processInventoryDeduction()</code> actualmente solo busca <code>\$item['id']</code> y lo trata como product_id.</p>";
echo "<p><strong>NO está descontando fixed_items ni selections del combo.</strong></p>";
echo "<p>Entonces cuando vendes un combo:</p>";
echo "<ul>";
echo "<li>❌ NO descuenta los productos fijos (completo, papas)</li>";
echo "<li>❌ NO descuenta la bebida seleccionada</li>";
echo "<li>❌ NO descuenta ingredientes de esos productos</li>";
echo "</ul>";
echo "</div>";

// 7. SOLUCIÓN PROPUESTA
echo "<h2>✅ 7. SOLUCIÓN PROPUESTA</h2>";
echo "<div style='background:#fff;padding:20px;border-radius:5px'>";
echo "<h3>Opción A: Modificar processInventoryDeduction()</h3>";
echo "<pre>
function processInventoryDeduction(\$pdo, \$cart_items) {
    foreach (\$cart_items as \$item) {
        \$is_combo = isset(\$item['type']) && \$item['type'] === 'combo';
        
        if (\$is_combo) {
            // Descontar fixed_items
            foreach (\$item['fixed_items'] as \$fixed) {
                deductProduct(\$pdo, \$fixed['product_id'], \$fixed['quantity']);
            }
            
            // Descontar selections (bebidas)
            foreach (\$item['selections'] as \$selection) {
                deductProduct(\$pdo, \$selection['id'], 1);
            }
        } else {
            // Producto normal
            deductProduct(\$pdo, \$item['id'], \$item['quantity']);
        }
    }
}

function deductProduct(\$pdo, \$product_id, \$quantity) {
    // Buscar receta del producto
    // Si tiene receta → descontar ingredientes
    // Si no → descontar stock directo
}
</pre>";
echo "<h3>Opción B: Eliminar combos de products</h3>";
echo "<p>Desactivar todos los products con category_id=8 para evitar confusión.</p>";
echo "<p>Solo usar tabla <code>combos</code> para combos.</p>";
echo "</div>";
echo "</div>";
