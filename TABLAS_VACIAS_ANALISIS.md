# Análisis de Tablas Vacías - APIs y Lógica a Eliminar

## ✅ TABLAS SEGURAS PARA ELIMINAR (Sin uso activo)

### 1. `ventas` (0 registros)
**Estado**: ❌ ELIMINAR - Reemplazada por `ventas_v2`
**APIs que la usan**:
- `app3/api/registrar_venta.php` - INSERT INTO ventas
- `app3/api/cleanup_fake_data.php` - DELETE FROM ventas

**Acción**: 
- Eliminar `registrar_venta.php` (obsoleto)
- Remover lógica de ventas en `cleanup_fake_data.php`

---

### 2. `customers` (0 registros)
**Estado**: ✅ SEGURO ELIMINAR - No hay referencias en código
**APIs**: Ninguna
**Acción**: Eliminar tabla directamente

---

### 3. `orders` (0 registros)
**Estado**: ⚠️ REVISAR - Tiene algunas referencias mínimas
**APIs que la usan**:
- `app3/api/get_pending_orders.php` - SELECT FROM orders
- `caja3/api/get_pending_orders.php` - SELECT FROM orders
- `caja3/api/registrar_cumpleanos.php` - INSERT INTO orders

**Acción**: 
- Verificar si `get_pending_orders.php` se usa (probablemente obsoleto, ahora se usa `tuu_orders`)
- Eliminar o actualizar `registrar_cumpleanos.php` para usar `tuu_orders`

---

### 4. `order_items` y `order_extras` (0 registros)
**Estado**: ✅ SEGURO ELIMINAR - Reemplazadas por `tuu_order_items`
**APIs**: Ninguna referencia directa
**Acción**: Eliminar ambas tablas

---

### 5. `winners` (0 registros)
**Estado**: ✅ SEGURO ELIMINAR - No hay referencias
**APIs**: Ninguna
**Acción**: Eliminar tabla

---

### 6. `search_analytics` (0 registros)
**Estado**: ✅ SEGURO ELIMINAR - No hay referencias
**APIs**: Ninguna
**Acción**: Eliminar tabla

---

## ⚠️ TABLAS CON USO ACTIVO - NO ELIMINAR

### 7. `user_notifications` (0 registros)
**Estado**: ✅ MANTENER - Sistema activo de notificaciones
**APIs activas**:
- `app3/api/notifications/get_notifications.php`
- `app3/api/notifications/mark_read.php`
- `app3/api/notifications/mark_all_read.php`
- `app3/api/notifications/send_notification.php`
- `caja3/api/notifications/*` (mismo sistema)

**Razón**: Sistema funcional, solo está vacía porque no hay notificaciones aún

---

### 8. `user_coupons` (0 registros)
**Estado**: ✅ MANTENER - Sistema de cupones activo
**APIs activas**:
- `app3/api/coupons/get_user_coupons.php`
- `app3/api/coupons/use_coupon.php`
- `app3/api/coupons/create_coupon.php`

**Razón**: Sistema funcional de cupones/descuentos

---

### 9. `user_orders` y `user_order_items` (0 registros)
**Estado**: ⚠️ REVISAR - Usado en analytics
**APIs activas**:
- `app3/api/app/get_analytics.php`
- `app3/api/users/get_user_detail.php`
- `app3/api/get_analytics.php`
- `caja3/api/*` (mismas APIs)

**Razón**: Parece ser un sistema alternativo de órdenes. Verificar si se usa o si todo está en `tuu_orders`

---

### 10. `cash_register_sessions` (0 registros)
**Estado**: ✅ MANTENER - Sistema activo de caja
**APIs activas**:
- `caja3/api/get_cash_register_status.php`
- `caja3/api/open_cash_register.php`
- `caja3/api/close_cash_register.php`
- `caja3/api/setup_cash_register_table.php`

**Razón**: Sistema funcional de apertura/cierre de caja

---

### 11. `app_visits` (0 registros)
**Estado**: ✅ MANTENER - Sistema de tracking
**APIs activas**:
- `app3/api/cleanup_fake_data.php` - Limpieza y estadísticas
- `caja3/api/cleanup_fake_data.php`

**Razón**: Sistema de analytics de visitas

---

## 🔄 TABLAS DE CONCURSO - DECISIÓN PENDIENTE

### 12. `concurso_matches`, `concurso_pagos`, `concurso_participants` (0 registros)
**Estado**: ⚠️ DECISIÓN DE NEGOCIO
**APIs activas**: Múltiples en `app3/api/`:
- `track_concurso_visit.php`
- `concurso_registro.php`
- `get_concurso_live.php`
- `update_concurso_state.php`
- `tuu_callback_concurso.php`
- Y más...

**Razón**: Sistema completo de concursos. Decidir si:
- ✅ Mantener si planean hacer concursos futuros
- ❌ Eliminar si fue evento único y no se repetirá

---

## 💳 TABLAS TUU - MANTENER TODAS

### 13. Tablas TUU (0 registros en algunas)
**Tablas**:
- `tuu_pagos_online` ✅ MANTENER
- `tuu_payments` ✅ MANTENER
- `tuu_remote_payments` ✅ MANTENER
- `tuu_reports` ✅ MANTENER
- `tuu_sync_control` ✅ MANTENER

**Estado**: ✅ MANTENER TODAS - Sistema de pagos activo
**APIs**: 30+ endpoints en `app3/api/tuu/` y `app3/api/tuu-pagos-online/`

**Razón**: Sistema crítico de pagos en producción

---

## 📋 RESUMEN DE ACCIONES

### ❌ ELIMINAR INMEDIATAMENTE:
1. `ventas` - Obsoleta, usar `ventas_v2`
2. `customers` - Sin uso
3. `order_items` - Obsoleta, usar `tuu_order_items`
4. `order_extras` - Obsoleta
5. `winners` - Sin uso
6. `search_analytics` - Sin uso

### ⚠️ REVISAR Y DECIDIR:
1. `orders` - Verificar si `get_pending_orders.php` se usa
2. `user_orders` + `user_order_items` - Verificar si es sistema alternativo o duplicado
3. `concurso_*` (3 tablas) - Decisión de negocio

### ✅ MANTENER (Sistemas activos):
1. `user_notifications` - Sistema de notificaciones
2. `user_coupons` - Sistema de cupones
3. `cash_register_sessions` - Sistema de caja
4. `app_visits` - Analytics
5. `tuu_*` (5 tablas) - Sistema de pagos

---

## 🔧 ARCHIVOS PHP A ELIMINAR

```bash
# APIs obsoletas para eliminar:
app3/api/registrar_venta.php
app3/api/get_pending_orders.php  # Verificar primero
caja3/api/get_pending_orders.php  # Verificar primero
caja3/api/registrar_cumpleanos.php  # Actualizar o eliminar
```

## 📝 SCRIPT SQL PARA ELIMINAR TABLAS SEGURAS

```sql
-- EJECUTAR SOLO DESPUÉS DE VERIFICAR
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS order_extras;
DROP TABLE IF EXISTS winners;
DROP TABLE IF EXISTS search_analytics;

-- OPCIONAL: Si decides eliminar concursos
-- DROP TABLE IF EXISTS concurso_matches;
-- DROP TABLE IF EXISTS concurso_pagos;
-- DROP TABLE IF EXISTS concurso_participants;

-- OPCIONAL: Si confirmas que orders está obsoleta
-- DROP TABLE IF EXISTS orders;

-- OPCIONAL: Si confirmas que user_orders está duplicada
-- DROP TABLE IF EXISTS user_orders;
-- DROP TABLE IF EXISTS user_order_items;
```
