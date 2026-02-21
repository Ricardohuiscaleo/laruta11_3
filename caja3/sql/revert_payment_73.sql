-- Revertir pago manual id=73, user_id=98
-- Eliminar refund duplicado
DELETE FROM rl6_credit_transactions WHERE id = 73;

-- Dejar credito_usado en 43920 (valor correcto)
UPDATE usuarios 
SET credito_usado = 43920.00,
    fecha_ultimo_pago = NULL
WHERE id = 98;
