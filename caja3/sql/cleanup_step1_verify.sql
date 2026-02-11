-- PASO 1: VERIFICAR ÓRDENES DUPLICADAS
-- Ejecutar este SQL primero para confirmar qué se va a eliminar

-- Ver todas las órdenes del usuario Yojhans en el rango de tiempo
SELECT 
    id,
    order_number,
    customer_name,
    product_price as total,
    payment_status,
    payment_method,
    order_status,
    created_at
FROM tuu_orders
WHERE customer_name = 'Yojhans'
AND created_at >= '2025-11-04 22:36:00'
AND created_at <= '2025-11-04 22:44:00'
ORDER BY created_at;

-- Contar cuántos items tiene cada orden
SELECT 
    o.id,
    o.order_number,
    COUNT(oi.id) as items_count,
    o.created_at
FROM tuu_orders o
LEFT JOIN tuu_order_items oi ON o.id = oi.order_id
WHERE o.customer_name = 'Yojhans'
AND o.created_at >= '2025-11-04 22:36:00'
AND o.created_at <= '2025-11-04 22:44:00'
GROUP BY o.id
ORDER BY o.created_at;

-- Ver qué órdenes se van a ELIMINAR (todas menos la primera)
SELECT 
    id,
    order_number,
    'SE ELIMINARÁ' as accion,
    created_at
FROM tuu_orders
WHERE id IN (335, 336, 337, 338, 339, 340, 341, 342, 343, 344, 345)
AND customer_name = 'Yojhans'
AND payment_status = 'unpaid'
ORDER BY id;

-- Ver qué orden se va a MANTENER (la primera)
SELECT 
    id,
    order_number,
    'SE MANTIENE' as accion,
    created_at
FROM tuu_orders
WHERE id = 334
AND customer_name = 'Yojhans';
