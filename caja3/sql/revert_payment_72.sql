-- Revertir pago manual id=72, user_id=98, amount=43920
-- 1. Eliminar la transacción de refund
DELETE FROM rl6_credit_transactions WHERE id = 72;

-- 2. Restaurar credito_usado sumando el monto revertido
UPDATE usuarios 
SET credito_usado = credito_usado + 43920.00,
    fecha_ultimo_pago = NULL
WHERE id = 98;
