-- ===================================================
-- REVERSE R11 AUTO-DEDUCT (Junio 2026)
-- ===================================================
-- Revierte el descuento automático de nómina R11
-- que se ejecutó el 1 de junio (mi3:r11-auto-deduct).
--
-- 1. Revisar cantidad de filas afectadas
-- 2. ROLLBACK si algo no cuadra
-- 3. COMMIT si todo OK
-- ===================================================

START TRANSACTION;

-- 1. Restaurar credito_r11_usado y bloquear usuarios
UPDATE usuarios u
INNER JOIN r11_credit_transactions t ON t.user_id = u.id
SET
    u.credito_r11_usado     = t.amount,
    u.credito_r11_bloqueado = 1,
    u.fecha_ultimo_pago_r11 = NULL
WHERE t.type = 'refund'
  AND t.description LIKE 'Descuento nómina%'
  AND DATE(t.created_at) = CURDATE();

-- 2. Eliminar ajustes de sueldo (descuento nómina)
DELETE a
FROM ajustes_sueldo a
INNER JOIN personal p ON p.id = a.personal_id
INNER JOIN r11_credit_transactions t ON t.user_id = p.user_id
WHERE a.monto = -t.amount
  AND a.concepto = t.description
  AND t.type = 'refund'
  AND t.description LIKE 'Descuento nómina%'
  AND DATE(t.created_at) = CURDATE();

-- 3. Eliminar las transacciones refund del auto-deduct
DELETE FROM r11_credit_transactions
WHERE type = 'refund'
  AND description LIKE 'Descuento nómina%'
  AND DATE(created_at) = CURDATE();

-- Revisar antes de committear
SELECT ROW_COUNT() AS filas_afectadas;
-- COMMIT;
-- ROLLBACK;
