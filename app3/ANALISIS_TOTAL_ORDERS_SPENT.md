# 📊 Análisis Completo: Columnas `total_orders` y `total_spent`

## 🔍 Estado Actual del Sistema

### **Tabla `usuarios`**
```sql
- total_orders INT (columna en tabla)
- total_spent DECIMAL (columna en tabla)
```

### **Tabla `tuu_orders`**
```sql
- Contiene todos los pedidos reales
- Campos: user_id, installment_amount, payment_status, order_status
```

---

## 📋 Flujo Actual del Sistema

### **1. Creación de Pedidos** (`create_order.php`)
- ✅ Inserta en `tuu_orders`
- ❌ **NO actualiza** `usuarios.total_orders`
- ❌ **NO actualiza** `usuarios.total_spent`

### **2. Login Manual** (`auth/login_manual.php`)
- ✅ Lee `usuarios.total_orders` y `usuarios.total_spent`
- ❌ Devuelve datos **desactualizados** al frontend

### **3. Check Session** (`auth/check_session.php`)
- ✅ **CALCULA en tiempo real** desde `tuu_orders`:
```php
SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN payment_status = 'paid' THEN installment_amount ELSE 0 END) as total_spent
FROM tuu_orders 
WHERE user_id = $user_id
```
- ✅ Devuelve datos **correctos** al frontend

### **4. Get User Orders** (`get_user_orders.php`)
- ✅ **CALCULA en tiempo real** desde `tuu_orders`:
```php
SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN payment_status = 'paid' THEN installment_amount ELSE 0 END) as total_spent
FROM tuu_orders 
WHERE user_id = ? AND order_status != 'cancelled'
```
- ✅ Devuelve datos **correctos** al frontend

### **5. Frontend** (`ProfileModalModern.jsx`, `MenuApp.jsx`)
- ✅ Usa `userStats.total_spent` (viene de `get_user_orders.php`)
- ✅ Calcula puntos: `Math.floor(total_spent / 10)`
- ✅ Muestra datos **correctos**

---

## ⚠️ Problema Identificado

Las columnas `usuarios.total_orders` y `usuarios.total_spent`:

1. **NO se actualizan automáticamente** cuando se crea un pedido
2. **NO se usan en el flujo principal** (frontend lee desde `tuu_orders`)
3. **Solo se usan en `login_manual.php`** (y devuelve datos incorrectos)
4. **Causan confusión** al tener datos desactualizados

---

## ✅ Soluciones Propuestas

### **Opción 1: ELIMINAR las columnas (RECOMENDADO)**

**Ventajas:**
- ✅ Elimina redundancia
- ✅ Elimina confusión
- ✅ Simplifica el sistema
- ✅ El sistema ya funciona sin ellas

**Desventajas:**
- ❌ Ninguna (no se usan realmente)

**Implementación:**
```sql
ALTER TABLE usuarios 
DROP COLUMN total_orders,
DROP COLUMN total_spent;
```

**Archivos a modificar:**
- `api/auth/login_manual.php` - Eliminar referencias a estas columnas

---

### **Opción 2: Mantener con TRIGGER automático**

**Ventajas:**
- ✅ Datos siempre sincronizados
- ✅ Queries más rápidos en `usuarios` (sin JOIN)

**Desventajas:**
- ❌ Complejidad adicional
- ❌ Overhead en cada INSERT/UPDATE
- ❌ No es necesario (el sistema ya funciona)

**Implementación:**
```sql
DELIMITER $$

CREATE TRIGGER sync_user_stats_insert
AFTER INSERT ON tuu_orders
FOR EACH ROW
BEGIN
    UPDATE usuarios 
    SET 
        total_orders = (
            SELECT COUNT(*) 
            FROM tuu_orders 
            WHERE user_id = NEW.user_id 
              AND payment_status = 'paid' 
              AND order_status != 'cancelled'
        ),
        total_spent = (
            SELECT COALESCE(SUM(installment_amount), 0)
            FROM tuu_orders 
            WHERE user_id = NEW.user_id 
              AND payment_status = 'paid' 
              AND order_status != 'cancelled'
        )
    WHERE id = NEW.user_id;
END$$

CREATE TRIGGER sync_user_stats_update
AFTER UPDATE ON tuu_orders
FOR EACH ROW
BEGIN
    UPDATE usuarios 
    SET 
        total_orders = (
            SELECT COUNT(*) 
            FROM tuu_orders 
            WHERE user_id = NEW.user_id 
              AND payment_status = 'paid' 
              AND order_status != 'cancelled'
        ),
        total_spent = (
            SELECT COALESCE(SUM(installment_amount), 0)
            FROM tuu_orders 
            WHERE user_id = NEW.user_id 
              AND payment_status = 'paid' 
              AND order_status != 'cancelled'
        )
    WHERE id = NEW.user_id;
END$$

DELIMITER ;
```

---

### **Opción 3: Script de sincronización manual**

**Ventajas:**
- ✅ Control manual
- ✅ Sin overhead en operaciones

**Desventajas:**
- ❌ Requiere ejecución manual/cron
- ❌ Datos pueden estar desactualizados

**Implementación:**
```sql
-- Script para sincronizar todos los usuarios
UPDATE usuarios u
LEFT JOIN (
    SELECT 
        user_id,
        COUNT(*) as orders_count,
        COALESCE(SUM(installment_amount), 0) as spent_total
    FROM tuu_orders
    WHERE payment_status = 'paid' 
      AND order_status != 'cancelled'
    GROUP BY user_id
) o ON u.id = o.user_id
SET 
    u.total_orders = COALESCE(o.orders_count, 0),
    u.total_spent = COALESCE(o.spent_total, 0);
```

---

## 🎯 Recomendación Final

### **ELIMINAR las columnas** (Opción 1)

**Razones:**
1. El sistema **ya funciona correctamente** sin usarlas
2. El frontend **siempre consulta** desde `tuu_orders`
3. Mantenerlas solo **agrega complejidad innecesaria**
4. No hay **beneficio de performance** (pocas consultas)

**Pasos:**
1. Eliminar columnas de la tabla `usuarios`
2. Modificar `api/auth/login_manual.php` para no devolver esos campos
3. Listo ✅

---

## 📝 Archivos que Usan Estas Columnas

### **Backend (PHP):**
- ✅ `api/auth/login_manual.php` - Lee de `usuarios` (INCORRECTO)
- ✅ `api/auth/check_session.php` - Calcula desde `tuu_orders` (CORRECTO)
- ✅ `api/get_user_orders.php` - Calcula desde `tuu_orders` (CORRECTO)
- ✅ `api/get_users.php` - Lee de `usuarios` (para admin)
- ✅ `api/users/get_users.php` - Lee de `usuarios` (para admin)

### **Frontend (JSX):**
- ✅ `src/components/modals/ProfileModalModern.jsx` - Usa `userStats.total_spent`
- ✅ `src/components/modals/ProfileModal.jsx` - Usa `userStats.total_orders`
- ✅ `src/components/MenuApp.jsx` - Usa `userStats.total_spent`
- ✅ `src/components/CheckoutApp.jsx` - Usa `userStats.total_spent`

**Todos los componentes frontend usan `userStats` que viene de `get_user_orders.php` (datos correctos)**

---

## 🚀 Conclusión

El sistema **NO necesita** las columnas `total_orders` y `total_spent` en la tabla `usuarios`. 

**Acción recomendada:** ELIMINAR las columnas y simplificar el código.
