-- VERIFICAR CASHBACK Y HISTORIAL

-- 1. Ver saldo del usuario
SELECT user_id, balance, total_earned, total_used 
FROM user_wallet 
WHERE user_id = 5;

-- 2. Ver transacciones registradas
SELECT * FROM wallet_transactions 
WHERE user_id = 5 
ORDER BY created_at DESC;

-- 3. Contar transacciones por usuario
SELECT user_id, COUNT(*) as total_transacciones 
FROM wallet_transactions 
GROUP BY user_id;

-- 4. Ver si hay transacciones sin user_id correcto
SELECT * FROM wallet_transactions 
WHERE user_id > 1000 
ORDER BY created_at DESC;

-- 5. Ver estructura de wallet_transactions
DESCRIBE wallet_transactions;

-- 6. Ver últimas órdenes pagadas
SELECT order_number, user_id, product_price, payment_status, created_at 
FROM tuu_orders 
WHERE payment_status = 'paid' 
ORDER BY created_at DESC 
LIMIT 10;
