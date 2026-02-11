-- ============================================
-- QUERIES PARA VERIFICAR CÁLCULO DE PUNTOS
-- Base de datos: u958525313_app
-- ============================================

-- 1. VER USUARIO DE PRUEBA
SELECT 
    id,
    nombre,
    email,
    telefono,
    cashback_level_bronze,
    cashback_level_silver,
    cashback_level_gold
FROM usuarios 
WHERE email = 'ricardo.huiscaleo@gmail.com';

-- 2. VER TODOS LOS PEDIDOS DEL USUARIO
SELECT 
    order_number,
    tuu_amount,
    delivery_fee,
    (tuu_amount - COALESCE(delivery_fee, 0)) as monto_sin_delivery,
    payment_status,
    created_at
FROM tuu_orders 
WHERE user_id = (SELECT id FROM usuarios WHERE email = 'ricardo.huiscaleo@gmail.com')
ORDER BY created_at DESC;

-- 3. CALCULAR PUNTOS CORRECTAMENTE ($10 = 1 punto)
SELECT 
    COUNT(*) as total_pedidos,
    SUM(tuu_amount) as total_gastado_bruto,
    SUM(COALESCE(delivery_fee, 0)) as total_delivery,
    SUM(tuu_amount - COALESCE(delivery_fee, 0)) as total_sin_delivery,
    SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) as total_puntos_correctos,
    FLOOR(SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) / 1000) as total_sellos_correctos
FROM tuu_orders 
WHERE user_id = (SELECT id FROM usuarios WHERE email = 'ricardo.huiscaleo@gmail.com')
AND payment_status = 'paid';

-- 4. VER WALLET DEL USUARIO
SELECT 
    id,
    user_id,
    balance,
    total_earned,
    total_used,
    created_at,
    updated_at
FROM user_wallet 
WHERE user_id = (SELECT id FROM usuarios WHERE email = 'ricardo.huiscaleo@gmail.com');

-- 5. VER TRANSACCIONES DEL WALLET
SELECT 
    id,
    user_id,
    type,
    amount,
    description,
    balance_after,
    created_at
FROM wallet_transactions 
WHERE user_id = (SELECT id FROM usuarios WHERE email = 'ricardo.huiscaleo@gmail.com')
ORDER BY created_at DESC;

-- 6. COMPARAR: PUNTOS MOSTRADOS vs PUNTOS CORRECTOS
SELECT 
    u.id,
    u.nombre,
    u.email,
    (SELECT COUNT(*) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as total_pedidos,
    (SELECT SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as puntos_correctos,
    (SELECT FLOOR(SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) / 1000) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as sellos_correctos,
    (SELECT balance FROM user_wallet WHERE user_id = u.id) as saldo_wallet,
    (SELECT total_earned FROM user_wallet WHERE user_id = u.id) as total_ganado_wallet
FROM usuarios u
WHERE u.email = 'ricardo.huiscaleo@gmail.com';

-- 7. VER TODOS LOS USUARIOS CON WALLET
SELECT 
    u.id,
    u.nombre,
    u.email,
    w.balance,
    w.total_earned,
    w.total_used,
    (SELECT COUNT(*) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as total_pedidos,
    (SELECT SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as puntos_totales
FROM usuarios u
LEFT JOIN user_wallet w ON u.id = w.user_id
WHERE w.balance > 0 OR w.total_earned > 0
ORDER BY w.balance DESC;

-- 8. VERIFICAR CAMPOS EN tuu_orders
SELECT 
    order_number,
    tuu_amount,
    installment_amount,
    delivery_fee,
    payment_status
FROM tuu_orders 
WHERE user_id = (SELECT id FROM usuarios WHERE email = 'ricardo.huiscaleo@gmail.com')
LIMIT 3;

-- 9. CONTAR PEDIDOS POR ESTADO DE PAGO
SELECT 
    payment_status,
    COUNT(*) as cantidad,
    SUM(tuu_amount) as total
FROM tuu_orders 
WHERE user_id = (SELECT id FROM usuarios WHERE email = 'ricardo.huiscaleo@gmail.com')
GROUP BY payment_status;

-- 10. VER DETALLES COMPLETOS DE UN USUARIO
SELECT 
    u.id,
    u.nombre,
    u.email,
    u.telefono,
    u.cashback_level_bronze,
    u.cashback_level_silver,
    u.cashback_level_gold,
    w.balance as wallet_balance,
    w.total_earned as wallet_total_earned,
    w.total_used as wallet_total_used,
    (SELECT COUNT(*) FROM tuu_orders WHERE user_id = u.id) as total_pedidos,
    (SELECT COUNT(*) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as pedidos_pagados,
    (SELECT SUM(tuu_amount) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as total_gastado,
    (SELECT SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as puntos_totales,
    (SELECT FLOOR(SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) / 1000) FROM tuu_orders WHERE user_id = u.id AND payment_status = 'paid') as sellos_totales
FROM usuarios u
LEFT JOIN user_wallet w ON u.id = w.user_id
WHERE u.email = 'ricardo.huiscaleo@gmail.com';

-- ============================================
-- QUERIES PARA DEBUGGING
-- ============================================

-- 11. VERIFICAR ESTRUCTURA DE TABLA tuu_orders
DESCRIBE tuu_orders;

-- 12. VERIFICAR ESTRUCTURA DE TABLA user_wallet
DESCRIBE user_wallet;

-- 13. VERIFICAR ESTRUCTURA DE TABLA wallet_transactions
DESCRIBE wallet_transactions;

-- 14. VERIFICAR ESTRUCTURA DE TABLA usuarios
DESCRIBE usuarios;

-- 15. CONTAR USUARIOS CON WALLET
SELECT COUNT(*) as usuarios_con_wallet FROM user_wallet;

-- 16. CONTAR TRANSACCIONES DE WALLET
SELECT COUNT(*) as total_transacciones FROM wallet_transactions;

-- 17. SUMA TOTAL DE CASHBACK GENERADO
SELECT 
    SUM(balance) as saldo_total_disponible,
    SUM(total_earned) as total_cashback_generado,
    SUM(total_used) as total_cashback_usado,
    COUNT(*) as usuarios_con_wallet
FROM user_wallet;

-- 18. VER USUARIOS CON CASHBACK GENERADO
SELECT 
    u.id,
    u.nombre,
    u.email,
    w.balance,
    w.total_earned,
    u.cashback_level_bronze,
    u.cashback_level_silver,
    u.cashback_level_gold
FROM usuarios u
LEFT JOIN user_wallet w ON u.id = w.user_id
WHERE w.total_earned > 0
ORDER BY w.total_earned DESC;

-- 19. VERIFICAR PEDIDOS SIN DELIVERY_FEE
SELECT 
    order_number,
    tuu_amount,
    delivery_fee,
    payment_status
FROM tuu_orders 
WHERE delivery_fee IS NULL OR delivery_fee = 0
LIMIT 10;

-- 20. CALCULAR PUNTOS POR USUARIO (TOP 10)
SELECT 
    u.id,
    u.nombre,
    u.email,
    COUNT(DISTINCT o.id) as total_pedidos,
    SUM(FLOOR((o.tuu_amount - COALESCE(o.delivery_fee, 0)) / 10)) as puntos_totales,
    FLOOR(SUM(FLOOR((o.tuu_amount - COALESCE(o.delivery_fee, 0)) / 10)) / 1000) as sellos_totales
FROM usuarios u
LEFT JOIN tuu_orders o ON u.id = o.user_id AND o.payment_status = 'paid'
GROUP BY u.id, u.nombre, u.email
HAVING puntos_totales > 0
ORDER BY puntos_totales DESC
LIMIT 10;
