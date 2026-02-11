# 🔧 Solución: Error "Column 'user_id' cannot be null"

## 📋 Problema

Al confirmar pagos desde MiniComandas para órdenes creadas en CAJA (sin usuario logueado), el sistema generaba el error:

```json
{
    "success": false,
    "error": "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'user_id' cannot be null",
    "trace": "#0 /home/u958525313/domains/laruta11.cl/public_html/caja/api/confirm_transfer_payment.php(61): PDOStatement->execute()\n#1 {main}"
}
```

## 🎯 Causa Raíz

El sistema maneja dos tipos de órdenes:

1. **Órdenes de APP**: Creadas por clientes con login → `user_id = 123` (valor real)
2. **Órdenes de CAJA**: Creadas por cajero sin usuario → `user_id = NULL`

El trigger `auto_generate_cashback` se ejecutaba para TODAS las órdenes, incluyendo las de CAJA, e intentaba:

```sql
-- ❌ CÓDIGO PROBLEMÁTICO (antes)
IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
    -- Intenta hacer WHERE user_id = NULL (falla)
    SELECT ... FROM tuu_orders WHERE user_id = NEW.user_id;
    
    -- Intenta insertar NULL en user_wallet (error)
    INSERT INTO user_wallet (user_id, balance, ...) VALUES (NEW.user_id, 0, ...);
END IF;
```

## ✅ Solución Implementada

Agregamos validación `AND NEW.user_id IS NOT NULL` al trigger para que solo se ejecute cuando hay un usuario real:

```sql
DROP TRIGGER IF EXISTS auto_generate_cashback;

DELIMITER $$

CREATE TRIGGER auto_generate_cashback
AFTER UPDATE ON tuu_orders
FOR EACH ROW
BEGIN
    DECLARE total_stamps INT;
    DECLARE user_bronze TINYINT;
    DECLARE user_silver TINYINT;
    DECLARE user_gold TINYINT;
    DECLARE current_balance DECIMAL(10,2);
    
    -- ✅ VALIDACIÓN CRÍTICA: Solo ejecutar si hay usuario
    IF NEW.payment_status = 'paid' 
       AND OLD.payment_status != 'paid' 
       AND NEW.user_id IS NOT NULL THEN
        
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
            SELECT balance INTO current_balance FROM user_wallet WHERE user_id = NEW.user_id;
            
            UPDATE user_wallet
            SET balance = balance + 6000, total_earned = total_earned + 6000
            WHERE user_id = NEW.user_id;
            
            INSERT INTO wallet_transactions (user_id, type, amount, description, balance_before, balance_after)
            VALUES (NEW.user_id, 'earned', 6000, '🥉 Cashback Bronce (6 sellos)', current_balance, current_balance + 6000);
            
            UPDATE usuarios SET cashback_level_bronze = 1 WHERE id = NEW.user_id;
        END IF;
        
        -- NIVEL PLATA: 12 sellos = $12,000
        IF total_stamps >= 12 AND user_silver = 0 THEN
            SELECT balance INTO current_balance FROM user_wallet WHERE user_id = NEW.user_id;
            
            UPDATE user_wallet
            SET balance = balance + 12000, total_earned = total_earned + 12000
            WHERE user_id = NEW.user_id;
            
            INSERT INTO wallet_transactions (user_id, type, amount, description, balance_before, balance_after)
            VALUES (NEW.user_id, 'earned', 12000, '🥈 Cashback Plata (12 sellos)', current_balance, current_balance + 12000);
            
            UPDATE usuarios SET cashback_level_silver = 1 WHERE id = NEW.user_id;
        END IF;
        
        -- NIVEL ORO: 18 sellos = $18,000
        IF total_stamps >= 18 AND user_gold = 0 THEN
            SELECT balance INTO current_balance FROM user_wallet WHERE user_id = NEW.user_id;
            
            UPDATE user_wallet
            SET balance = balance + 18000, total_earned = total_earned + 18000
            WHERE user_id = NEW.user_id;
            
            INSERT INTO wallet_transactions (user_id, type, amount, description, balance_before, balance_after)
            VALUES (NEW.user_id, 'earned', 18000, '🥇 Cashback Oro (18 sellos)', current_balance, current_balance + 18000);
            
            UPDATE usuarios SET cashback_level_gold = 1 WHERE id = NEW.user_id;
        END IF;
        
    END IF;
END$$

DELIMITER ;
```

## 🎯 Cambio Clave

**Línea 11**: Agregamos `AND NEW.user_id IS NOT NULL`

```sql
-- ANTES (causaba error)
IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN

-- DESPUÉS (funciona correctamente)
IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' AND NEW.user_id IS NOT NULL THEN
```

## ✅ Resultado

Después de aplicar la solución:

```json
{
    "success": true,
    "message": "Pago por tarjeta confirmado exitosamente",
    "order_number": "T11-1764211178-1818",
    "payment_method": "card"
}
```

## 📊 Funcionamiento del Sistema

### Órdenes de APP (con usuario)
```
Usuario hace pedido → user_id = 123
↓
Confirma pago → payment_status = 'paid'
↓
Trigger se ejecuta → Calcula sellos y genera cashback
↓
✅ Cashback agregado a wallet
```

### Órdenes de CAJA (sin usuario)
```
Cajero crea orden → user_id = NULL
↓
Confirma pago → payment_status = 'paid'
↓
Trigger se salta → No ejecuta código de cashback
↓
✅ Orden procesada sin error
```

## 🎁 Sistema de Recompensas

El trigger genera cashback automáticamente en 3 niveles:

| Nivel | Sellos | Gasto Total | Cashback |
|-------|--------|-------------|----------|
| 🥉 Bronce | 6 | $60.000 | $6.000 |
| 🥈 Plata | 12 | $120.000 | $12.000 |
| 🥇 Oro | 18 | $180.000 | $18.000 |

**Cálculo**: Cada $10.000 gastados = 1 sello

## 🔍 Triggers Activos

Después de la solución, el sistema tiene 2 triggers:

1. ✅ **auto_generate_cashback** - Genera cashback automático (con validación NULL)
2. ✅ **auto_update_payment_status** - Actualiza estados de pago

## 📝 Archivos Modificados

- **Base de Datos**: Trigger `auto_generate_cashback` actualizado
- **API**: `/caja/api/confirm_transfer_payment.php` (sin cambios, funciona correctamente)

## 🚀 Estado Final

- ✅ Órdenes de CAJA se confirman sin errores
- ✅ Órdenes de APP generan cashback automáticamente
- ✅ MiniComandas procesa todos los métodos de pago
- ✅ Sistema de recompensas funcionando correctamente
- ✅ Wallet y transacciones operativas

## 📌 Notas Importantes

1. **No eliminar el trigger**: `auto_generate_cashback` es crítico para el sistema de recompensas
2. **Validación NULL**: Siempre validar `user_id IS NOT NULL` en triggers que usen este campo
3. **Órdenes de CAJA**: Diseñadas para no tener usuario (user_id = NULL es correcto)
4. **Estadísticas**: Se calculan dinámicamente desde `tuu_orders` cuando se necesitan

## 🎯 Lecciones Aprendidas

1. Los triggers deben validar campos NULL antes de usarlos
2. El sistema debe soportar órdenes con y sin usuario
3. El cashback solo aplica para usuarios registrados (APP)
4. Las órdenes de CAJA no generan recompensas (correcto por diseño)

---

**Fecha de Solución**: 27 de Noviembre, 2025  
**Sistema**: La Ruta 11 - Gestión de Restaurante  
**Estado**: ✅ Resuelto y en Producción
