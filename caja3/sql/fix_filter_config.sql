-- Actualizar filter_config para que coincida con los IDs reales de la base de datos

-- 1. PAPAS: category_id=12, subcategory_id=57
UPDATE menu_categories 
SET filter_config = '{"category_id": 12, "subcategory_ids": [57]}'
WHERE slug = 'papas';

-- 2. BEBIDAS: category_id=5 (Snacks), subcategory_ids=[11,10,27,28]
UPDATE menu_categories 
SET filter_config = '{"category_id": 5, "subcategory_ids": [11, 10, 27, 28]}'
WHERE slug = 'bebidas';

-- 3. COMBOS: category_id=8
UPDATE menu_categories 
SET filter_config = '{"category_id": 8, "subcategory_ids": []}'
WHERE slug = 'combos';

-- 4. HAMBURGUESAS: category_id=3, subcategory_id=6
UPDATE menu_categories 
SET filter_config = '{"category_id": 3, "subcategory_ids": [6]}'
WHERE slug = 'hamburguesas';

-- 5. HAMBURGUESAS 100G: category_id=3, subcategory_id=5
UPDATE menu_categories 
SET filter_config = '{"category_id": 3, "subcategory_ids": [5]}'
WHERE slug = 'hamburguesas_100g';

-- 6. CHURRASCOS: category_id=2
UPDATE menu_categories 
SET filter_config = '{"category_id": 2, "subcategory_ids": []}'
WHERE slug = 'churrascos';

-- 7. COMPLETOS: category_id=4
UPDATE menu_categories 
SET filter_config = '{"category_id": 4, "subcategory_ids": []}'
WHERE slug = 'completos';

-- 8. PIZZAS: category_id=5, subcategory_id=60
UPDATE menu_categories 
SET filter_config = '{"category_id": 5, "subcategory_ids": [60]}'
WHERE slug = 'pizzas';

-- Verificar los cambios
SELECT slug, display_name, filter_config, is_active, sort_order 
FROM menu_categories 
ORDER BY sort_order;
