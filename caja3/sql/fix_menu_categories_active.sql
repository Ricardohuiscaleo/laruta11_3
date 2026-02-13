-- Activar todas las categorías del menú
UPDATE menu_categories SET is_active = 1;

-- Activar todas las subcategorías del menú
UPDATE menu_subcategories SET is_active = 1;

-- Verificar el resultado
SELECT 
    mc.id,
    mc.category_key,
    mc.display_name,
    mc.is_active,
    mc.sort_order,
    COUNT(ms.id) as subcategories_count
FROM menu_categories mc
LEFT JOIN menu_subcategories ms ON mc.id = ms.menu_category_id
GROUP BY mc.id
ORDER BY mc.sort_order;
