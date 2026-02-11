-- 🔍 VERIFICACIÓN DE COMBOS E INVENTARIO

-- 1. Ver todos los combos activos
SELECT 
    id,
    name,
    price,
    active
FROM combos
WHERE active = 1;

-- 2. Ver combos SIN receta (PROBLEMA)
SELECT 
    c.id,
    c.name,
    'SIN RECETA' as problema
FROM combos c
LEFT JOIN product_recipes pr ON c.id = pr.product_id
WHERE c.active = 1
AND pr.product_id IS NULL;

-- 3. Ver recetas de combos (ingredientes)
SELECT 
    c.id as combo_id,
    c.name as combo_name,
    i.name as ingrediente,
    pr.quantity,
    pr.unit
FROM combos c
JOIN product_recipes pr ON c.id = pr.product_id
JOIN ingredients i ON pr.ingredient_id = i.id
WHERE c.active = 1
ORDER BY c.id, i.name;

-- 4. Ver fixed_items de combos
SELECT 
    c.id as combo_id,
    c.name as combo_name,
    p.name as producto_fijo,
    ci.quantity
FROM combos c
JOIN combo_items ci ON c.combo_id = ci.combo_id
JOIN products p ON ci.product_id = p.id
WHERE c.active = 1
AND ci.is_selectable = 0
ORDER BY c.id;

-- 5. Ver selections de combos (bebidas)
SELECT 
    c.id as combo_id,
    c.name as combo_name,
    cs.selection_group,
    p.name as opcion,
    cs.additional_price
FROM combos c
JOIN combo_selections cs ON c.id = cs.combo_id
JOIN products p ON cs.product_id = p.id
WHERE c.active = 1
ORDER BY c.id, cs.selection_group;

-- 6. Buscar bolsas/packaging en recetas de combos (PROBLEMA)
SELECT 
    c.name as combo,
    i.name as ingrediente,
    pr.quantity,
    pr.unit
FROM combos c
JOIN product_recipes pr ON c.id = pr.product_id
JOIN ingredients i ON pr.ingredient_id = i.id
WHERE c.active = 1
AND (
    i.name LIKE '%bolsa%' OR 
    i.name LIKE '%packaging%' OR
    i.name LIKE '%delivery%' OR
    i.name LIKE '%envase%'
);

-- 7. Ver última venta de combo con transacciones
SELECT 
    o.order_number,
    o.created_at,
    oi.product_name,
    oi.quantity,
    COUNT(it.id) as transacciones_registradas
FROM tuu_orders o
JOIN tuu_order_items oi ON o.id = oi.order_id
LEFT JOIN inventory_transactions it ON oi.id = it.order_item_id
WHERE oi.item_type = 'combo'
AND o.payment_status = 'paid'
GROUP BY o.order_number, oi.id
ORDER BY o.created_at DESC
LIMIT 10;
