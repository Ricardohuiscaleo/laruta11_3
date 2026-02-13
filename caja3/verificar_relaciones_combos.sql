-- 🔍 VERIFICAR Y CORREGIR RELACIONES DE COMBOS

-- 1. Ver combos activos y sus relaciones
SELECT 
    c.id as combo_id,
    c.name as combo_name,
    c.price,
    COUNT(DISTINCT ci.id) as items_count,
    COUNT(DISTINCT cs.id) as selections_count
FROM combos c
LEFT JOIN combo_items ci ON c.id = ci.combo_id
LEFT JOIN combo_selections cs ON c.id = cs.combo_id
WHERE c.active = 1
GROUP BY c.id
ORDER BY c.id;

-- 2. Ver combos SIN items (PROBLEMA)
SELECT 
    c.id,
    c.name,
    'SIN ITEMS' as problema
FROM combos c
LEFT JOIN combo_items ci ON c.id = ci.combo_id
WHERE c.active = 1
AND ci.id IS NULL;

-- 3. Ver combos SIN selections (PROBLEMA)
SELECT 
    c.id,
    c.name,
    'SIN BEBIDAS' as problema
FROM combos c
LEFT JOIN combo_selections cs ON c.id = cs.combo_id
WHERE c.active = 1
AND cs.id IS NULL;

-- 4. Ver detalle completo de un combo específico (ejemplo: combo_id = 1)
SELECT 
    'COMBO' as tipo,
    c.id,
    c.name,
    c.price,
    NULL as product_name,
    NULL as selection_group
FROM combos c
WHERE c.id = 1

UNION ALL

SELECT 
    'ITEM FIJO' as tipo,
    ci.combo_id,
    NULL,
    NULL,
    p.name as product_name,
    NULL
FROM combo_items ci
JOIN products p ON ci.product_id = p.id
WHERE ci.combo_id = 1

UNION ALL

SELECT 
    'SELECCIÓN' as tipo,
    cs.combo_id,
    NULL,
    NULL,
    p.name as product_name,
    cs.selection_group
FROM combo_selections cs
JOIN products p ON cs.product_id = p.id
WHERE cs.combo_id = 1;
