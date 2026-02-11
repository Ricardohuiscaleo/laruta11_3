-- ============================================
-- Script: Actualizar cost_price de productos
-- ============================================
-- Este script actualiza el campo cost_price de todos los productos
-- basándose en el costo calculado desde sus recetas.
-- 
-- Ejecutar periódicamente cuando cambien precios de ingredientes.
-- ============================================

-- PASO 1: Actualizar productos que tienen receta
UPDATE products p
SET cost_price = (
    SELECT COALESCE(SUM(
        i.cost_per_unit * pr.quantity * 
        CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
    ), p.cost_price)
    FROM product_recipes pr
    JOIN ingredients i ON pr.ingredient_id = i.id
    WHERE pr.product_id = p.id 
    AND i.is_active = 1
    GROUP BY pr.product_id
)
WHERE EXISTS (
    SELECT 1 
    FROM product_recipes pr 
    WHERE pr.product_id = p.id
);

-- PASO 2: Mostrar productos actualizados
SELECT 
    p.id,
    p.name,
    p.cost_price as costo_actualizado,
    (
        SELECT SUM(
            i.cost_per_unit * pr.quantity * 
            CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
        )
        FROM product_recipes pr
        JOIN ingredients i ON pr.ingredient_id = i.id
        WHERE pr.product_id = p.id AND i.is_active = 1
    ) as costo_desde_receta,
    (
        SELECT COUNT(*) 
        FROM product_recipes pr 
        WHERE pr.product_id = p.id
    ) as num_ingredientes
FROM products p
WHERE EXISTS (
    SELECT 1 
    FROM product_recipes pr 
    WHERE pr.product_id = p.id
)
ORDER BY p.name;

-- ============================================
-- RESULTADO ESPERADO:
-- ============================================
-- Todos los productos con receta tendrán su cost_price
-- actualizado al costo real calculado desde ingredientes.
-- ============================================
