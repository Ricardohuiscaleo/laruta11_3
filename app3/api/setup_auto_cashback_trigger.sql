-- ============================================
-- TRIGGER AUTOMÁTICO PARA CASHBACK
-- Se ejecuta cada vez que se actualiza un pedido a 'paid'
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS auto_generate_cashback$$

CREATE TRIGGER auto_generate_cashback
AFTER UPDATE ON tuu_orders
FOR EACH ROW
BEGIN
    DECLARE total_stamps INT;
    DECLARE user_bronze TINYINT;
    DECLARE user_silver TINYINT;
    DECLARE user_gold TINYINT;
    DECLARE current_balance DECIMAL(10,2);
    
    -- Solo ejecutar si el pedido cambió a 'paid'
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        
        -- Calcular sellos del usuario
        SELECT FLOOR(SUM(FLOOR((installment_amount - COALESCE(delivery_fee, 0)) / 10)) / 1000)
        INTO total_stamps
        FROM tuu_orders
        WHERE user_id = NEW.user_id AND payment_status = 'paid';
        
        -- Obtener niveles actuales del usuario
        SELECT cashback_level_bronze, cashback_level_silver, cashback_level_gold
        INTO user_bronze, user_silver, user_gold
        FROM usuarios
        WHERE id = NEW.user_id;
        
        -- Crear wallet si no existe
        INSERT INTO user_wallet (user_id, balance, total_earned, total_used)
        VALUES (NEW.user_id, 0, 0, 0)
        ON DUPLICATE KEY UPDATE user_id = user_id;
        
        -- NIVEL BRONCE: 6 sellos = $6,000
        IF total_stamps >= 6 AND user_bronze = 0 THEN
            -- Obtener balance actual
            SELECT balance INTO current_balance
            FROM user_wallet
            WHERE user_id = NEW.user_id;
            
            -- Actualizar wallet
            UPDATE user_wallet
            SET balance = balance + 6000,
                total_earned = total_earned + 6000
            WHERE user_id = NEW.user_id;
            
            -- Registrar transacción
            INSERT INTO wallet_transactions (user_id, type, amount, description, balance_before, balance_after)
            VALUES (NEW.user_id, 'earned', 6000, '🥉 Cashback Nivel Bronce (6 sellos)', current_balance, current_balance + 6000);
            
            -- Marcar nivel como completado
            UPDATE usuarios
            SET cashback_level_bronze = 1
            WHERE id = NEW.user_id;
        END IF;
        
        -- NIVEL PLATA: 12 sellos = $12,000
        IF total_stamps >= 12 AND user_silver = 0 THEN
            SELECT balance INTO current_balance
            FROM user_wallet
            WHERE user_id = NEW.user_id;
            
            UPDATE user_wallet
            SET balance = balance + 12000,
                total_earned = total_earned + 12000
            WHERE user_id = NEW.user_id;
            
            INSERT INTO wallet_transactions (user_id, type, amount, description, balance_before, balance_after)
            VALUES (NEW.user_id, 'earned', 12000, '🥈 Cashback Nivel Plata (12 sellos)', current_balance, current_balance + 12000);
            
            UPDATE usuarios
            SET cashback_level_silver = 1
            WHERE id = NEW.user_id;
        END IF;
        
        -- NIVEL ORO: 18 sellos = $18,000
        IF total_stamps >= 18 AND user_gold = 0 THEN
            SELECT balance INTO current_balance
            FROM user_wallet
            WHERE user_id = NEW.user_id;
            
            UPDATE user_wallet
            SET balance = balance + 18000,
                total_earned = total_earned + 18000
            WHERE user_id = NEW.user_id;
            
            INSERT INTO wallet_transactions (user_id, type, amount, description, balance_before, balance_after)
            VALUES (NEW.user_id, 'earned', 18000, '🥇 Cashback Nivel Oro (18 sellos)', current_balance, current_balance + 18000);
            
            UPDATE usuarios
            SET cashback_level_gold = 1
            WHERE id = NEW.user_id;
        END IF;
        
    END IF;
END$$

DELIMITER ;

-- Verificar que el trigger se creó correctamente
SHOW TRIGGERS LIKE 'tuu_orders';
