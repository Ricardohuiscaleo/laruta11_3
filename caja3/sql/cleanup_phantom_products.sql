-- ============================================
-- LIMPIEZA DE PRODUCTOS FANTASMA
-- Productos con IDs basados en timestamps
-- ============================================

-- 1. VER productos fantasma (IDs > 1000000000)
SELECT 
    id,
    name,
    category_id,
    subcategory_id,
    image_url,
    is_active,
    created_at
FROM products 
WHERE id > 1000000000
ORDER BY id DESC;

-- 2. CONTAR productos fantasma
SELECT COUNT(*) as total_phantom_products
FROM products 
WHERE id > 1000000000;

-- 3. VER productos válidos (IDs < 1000)
SELECT 
    id,
    name,
    category_id,
    subcategory_id,
    is_active
FROM products 
WHERE id < 1000
ORDER BY id ASC;

-- 4. ELIMINAR productos fantasma (EJECUTAR CON CUIDADO)
-- Descomenta la siguiente línea solo cuando estés seguro:
-- DELETE FROM products WHERE id > 1000000000;

-- 5. VERIFICAR que no haya referencias en otras tablas
SELECT 'reviews' as tabla, COUNT(*) as referencias
FROM reviews 
WHERE product_id > 1000000000
UNION ALL
SELECT 'order_items' as tabla, COUNT(*) as referencias
FROM order_items 
WHERE product_id > 1000000000
UNION ALL
SELECT 'combo_items' as tabla, COUNT(*) as referencias
FROM combo_items 
WHERE product_id > 1000000000;

-- 6. LIMPIAR referencias huérfanas (si existen)
-- DELETE FROM reviews WHERE product_id > 1000000000;
-- DELETE FROM order_items WHERE product_id > 1000000000;
-- DELETE FROM combo_items WHERE product_id > 1000000000;
