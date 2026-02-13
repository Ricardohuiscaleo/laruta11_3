-- 🔄 MIGRACIÓN DE DATOS DE COMBOS
-- Copiar datos de tablas viejas a nuevas

-- 1. Migrar combo_items (productos fijos del combo)
-- De: combo_items_old → A: combo_items
INSERT INTO combo_items (combo_id, product_id, quantity, is_selectable, created_at)
SELECT 
    combo_id,
    product_id,
    quantity,
    is_selectable,
    NOW()
FROM combo_items_old
WHERE NOT EXISTS (
    SELECT 1 FROM combo_items ci 
    WHERE ci.combo_id = combo_items_old.combo_id 
    AND ci.product_id = combo_items_old.product_id
);

-- 2. Migrar combo_selections (opciones seleccionables como bebidas)
-- De: combo_selections_old → A: combo_selections
INSERT INTO combo_selections (combo_id, product_id, selection_group, additional_price, created_at)
SELECT 
    combo_id,
    product_id,
    selection_group,
    additional_price,
    NOW()
FROM combo_selections_old
WHERE NOT EXISTS (
    SELECT 1 FROM combo_selections cs 
    WHERE cs.combo_id = combo_selections_old.combo_id 
    AND cs.product_id = combo_selections_old.product_id
    AND cs.selection_group = combo_selections_old.selection_group
);

-- 3. Verificar migración
SELECT 
    'combo_items' as tabla,
    COUNT(*) as registros_migrados
FROM combo_items
UNION ALL
SELECT 
    'combo_selections' as tabla,
    COUNT(*) as registros_migrados
FROM combo_selections;

-- 4. Ver combos con sus items y selections
SELECT 
    c.id,
    c.name,
    COUNT(DISTINCT ci.id) as items_fijos,
    COUNT(DISTINCT cs.id) as opciones_seleccionables
FROM combos c
LEFT JOIN combo_items ci ON c.combo_id = ci.combo_id
LEFT JOIN combo_selections cs ON c.id = cs.combo_id
WHERE c.active = 1
GROUP BY c.id, c.name
ORDER BY c.id;
