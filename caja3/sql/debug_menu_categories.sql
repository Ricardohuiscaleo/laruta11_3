-- 1. Ver todas las categorías del menú
SELECT * FROM menu_categories ORDER BY sort_order;

-- 2. Ver todas las subcategorías
SELECT 
    ms.*,
    mc.display_name as category_name
FROM menu_subcategories ms
LEFT JOIN menu_categories mc ON ms.menu_category_id = mc.id
ORDER BY ms.menu_category_id, ms.sort_order;

-- 3. Ver el JSON completo que devuelve el API
SELECT 
    mc.id,
    mc.category_key,
    mc.display_name,
    mc.is_active,
    mc.sort_order,
    mc.filter_config,
    GROUP_CONCAT(
        JSON_OBJECT(
            'id', ms.id,
            'subcategory_key', ms.subcategory_key,
            'display_name', ms.display_name,
            'is_active', ms.is_active,
            'sort_order', ms.sort_order
        )
    ) as subcategories
FROM menu_categories mc
LEFT JOIN menu_subcategories ms ON mc.id = ms.menu_category_id
GROUP BY mc.id
ORDER BY mc.sort_order;

-- 4. Contar registros
SELECT 
    'menu_categories' as tabla,
    COUNT(*) as total,
    SUM(is_active) as activas
FROM menu_categories
UNION ALL
SELECT 
    'menu_subcategories' as tabla,
    COUNT(*) as total,
    SUM(is_active) as activas
FROM menu_subcategories;
