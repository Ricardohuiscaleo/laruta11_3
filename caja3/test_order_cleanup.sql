-- ============================================
-- SCRIPT DE PRUEBA: Crear, Verificar y Limpiar Pedido Test
-- ============================================
-- IMPORTANTE: Reemplaza 'T11-XXXXXXXXXX-XXXX' con el número de pedido real después de crearlo
-- ============================================

-- PASO 1: Obtener stock ANTES del pedido (ejecutar ANTES de crear el pedido)
-- ============================================
SELECT 
    id as ingredient_id,
    name,
    current_stock,
    unit
FROM ingredientes
WHERE id IN (
    -- IDs de ingredientes que usa el Combo Completo (ajusta según tu combo)
    SELECT ingredient_id 
    FROM recetas 
    WHERE product_id = 14  -- ID del Combo Completo
)
ORDER BY id;

-- Guarda estos valores para comparar después


-- PASO 2: VERIFICAR el pedido y transacciones (ejecutar DESPUÉS de pagar)
-- ============================================
-- Reemplaza 'T11-XXXXXXXXXX-XXXX' con el número real del pedido

SET @order_number = 'T11-XXXXXXXXXX-XXXX';  -- ⚠️ REEMPLAZAR CON NÚMERO REAL

-- Ver items del pedido
SELECT 
    id as order_item_id,
    product_id,
    item_type,
    quantity,
    combo_data
FROM tuu_order_items
WHERE order_id = (SELECT id FROM tuu_orders WHERE order_number = @order_number);

-- Ver transacciones de inventario con stock flow
SELECT 
    it.id,
    it.ingredient_id,
    i.name as ingredient_name,
    it.previous_stock,
    it.quantity as consumed,
    it.new_stock,
    it.order_item_id,
    it.created_at
FROM inventory_transactions it
LEFT JOIN ingredientes i ON it.ingredient_id = i.id
WHERE it.order_reference = @order_number
ORDER BY it.order_item_id, it.id;

-- Ver stock ACTUAL después del pedido
SELECT 
    id as ingredient_id,
    name,
    current_stock,
    unit
FROM ingredientes
WHERE id IN (
    SELECT DISTINCT ingredient_id 
    FROM inventory_transactions 
    WHERE order_reference = @order_number
)
ORDER BY id;


-- PASO 3: REVERTIR stock a valores originales
-- ============================================
-- ⚠️ EJECUTAR SOLO SI QUIERES LIMPIAR EL PEDIDO DE PRUEBA

-- Revertir stock de ingredientes usando previous_stock de las transacciones
UPDATE ingredientes i
INNER JOIN (
    SELECT 
        ingredient_id,
        MIN(previous_stock) as original_stock
    FROM inventory_transactions
    WHERE order_reference = @order_number
    GROUP BY ingredient_id
) t ON i.id = t.ingredient_id
SET i.current_stock = t.original_stock;

-- Verificar que se revirtió correctamente
SELECT 
    id as ingredient_id,
    name,
    current_stock,
    unit
FROM ingredientes
WHERE id IN (
    SELECT DISTINCT ingredient_id 
    FROM inventory_transactions 
    WHERE order_reference = @order_number
)
ORDER BY id;


-- PASO 4: ELIMINAR el pedido de prueba
-- ============================================
-- ⚠️ EJECUTAR SOLO DESPUÉS DE VERIFICAR QUE TODO ESTÁ CORRECTO

-- Eliminar transacciones de inventario
DELETE FROM inventory_transactions 
WHERE order_reference = @order_number;

-- Eliminar items del pedido
DELETE FROM tuu_order_items 
WHERE order_id = (SELECT id FROM tuu_orders WHERE order_number = @order_number);

-- Eliminar el pedido
DELETE FROM tuu_orders 
WHERE order_number = @order_number;

-- Verificar que se eliminó todo
SELECT COUNT(*) as remaining_transactions 
FROM inventory_transactions 
WHERE order_reference = @order_number;

SELECT COUNT(*) as remaining_orders 
FROM tuu_orders 
WHERE order_number = @order_number;


-- ============================================
-- RESUMEN DE PASOS:
-- ============================================
-- 1. Ejecuta PASO 1 para ver stock inicial
-- 2. Crea pedido de prueba con 2x Combo Completo desde la app/caja
-- 3. Paga el pedido desde minicomandas
-- 4. Reemplaza @order_number con el número real (ej: T11-1762819491-2705)
-- 5. Ejecuta PASO 2 para verificar transacciones y stock flow
-- 6. Verifica que order_item_id sea diferente para cada item duplicado
-- 7. Verifica que previous_stock → new_stock sea secuencial
-- 8. Ejecuta PASO 3 para revertir stock
-- 9. Ejecuta PASO 4 para eliminar el pedido
-- ============================================
