-- RECONSTRUIR HISTORIAL DE CASHBACK CON TRANSACCIONES INDIVIDUALES

-- 1. Limpiar transacciones de cashback incompletas
DELETE FROM wallet_transactions 
WHERE type = 'earned' 
AND balance_before IS NULL 
AND balance_after IS NULL;

-- 2. Reconstruir historial basado en órdenes pagadas
-- Para cada orden pagada, crear una transacción de cashback
INSERT INTO wallet_transactions (user_id, type, amount, order_id, description, balance_before, balance_after)
SELECT 
    o.user_id,
    'earned',
    ROUND(COALESCE(o.subtotal, o.product_price) * 0.01) as cashback_amount,
    o.order_number,
    CONCAT('Cashback 1% - Orden ', o.order_number),
    0 as balance_before,
    0 as balance_after
FROM tuu_orders o
WHERE o.user_id IS NOT NULL 
  AND o.payment_status = 'paid'
  AND o.order_number NOT IN (
    SELECT order_id FROM wallet_transactions 
    WHERE type = 'earned' AND order_id IS NOT NULL
  )
ORDER BY o.created_at;

-- 3. Verificar que el historial se creó correctamente
SELECT user_id, COUNT(*) as total_transacciones, SUM(amount) as total_cashback
FROM wallet_transactions
WHERE type = 'earned'
GROUP BY user_id
ORDER BY total_cashback DESC;

-- 4. Ver historial del usuario 4
SELECT * FROM wallet_transactions 
WHERE user_id = 4 
ORDER BY created_at DESC;
