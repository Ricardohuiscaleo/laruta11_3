# Análisis de Tablas Vacías - APIs y Lógica a Eliminar

## 🔴 CONFIRMADO: Todas las tablas con 0 registros NO SE ESTÁN USANDO

Basado en los datos reales de producción, las tablas con 0 registros tienen APIs pero NO se ejecutan.

---

## ❌ ELIMINAR TODAS ESTAS TABLAS Y SUS APIs

### 1. `ventas` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/registrar_venta.php`
- Lógica en `app3/api/cleanup_fake_data.php`
- Lógica en `caja3/api/cleanup_fake_data.php`

**Razón**: Obsoleta, reemplazada por `ventas_v2`

---

### 2. `customers` (0 registros) ❌
**APIs**: Ninguna
**Razón**: Sin uso

---

### 3. `orders` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/get_pending_orders.php`
- `caja3/api/get_pending_orders.php`
- Lógica en `caja3/api/registrar_cumpleanos.php`

**Razón**: Obsoleta, ahora se usa `tuu_orders` (979 registros)

---

### 4. `order_items` (0 registros) ❌
**APIs**: Ninguna referencia directa
**Razón**: Obsoleta, ahora se usa `tuu_order_items` (2,376 registros)

---

### 5. `order_extras` (0 registros) ❌
**APIs**: Ninguna
**Razón**: Sin uso

---

### 6. `winners` (0 registros) ❌
**APIs**: Ninguna
**Razón**: Sin uso

---

### 7. `search_analytics` (0 registros) ❌
**APIs**: Ninguna
**Razón**: Sin uso

---

### 8. `user_notifications` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/notifications/get_notifications.php`
- `app3/api/notifications/mark_read.php`
- `app3/api/notifications/mark_all_read.php`
- `app3/api/notifications/send_notification.php`
- `caja3/api/notifications/*` (todos)
- Lógica en `app3/api/tracker/update_kanban_status.php`

**Razón**: Sistema implementado pero NUNCA usado (0 registros)

---

### 9. `user_coupons` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/coupons/get_user_coupons.php`
- `app3/api/coupons/use_coupon.php`
- `app3/api/coupons/create_coupon.php`

**Razón**: Sistema implementado pero NUNCA usado

---

### 10. `user_orders` (0 registros) ❌
### 11. `user_order_items` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/app/get_analytics.php` (lógica específica)
- `app3/api/users/get_user_detail.php` (lógica específica)
- `app3/api/get_analytics.php` (lógica específica)
- `app3/api/get_user_detail.php` (lógica específica)
- `caja3/api/*` (mismas APIs)

**Razón**: Sistema duplicado, se usa `tuu_orders` + `tuu_order_items`

---

### 12. `cash_register_sessions` (0 registros) ❌
**APIs a eliminar**:
- `caja3/api/get_cash_register_status.php`
- `caja3/api/open_cash_register.php`
- `caja3/api/close_cash_register.php`
- `caja3/api/setup_cash_register_table.php`

**Razón**: Sistema implementado pero NUNCA usado. Probablemente se usa otro sistema de caja.

---

### 13. `app_visits` (0 registros) ❌
**APIs a eliminar**:
- Lógica en `app3/api/cleanup_fake_data.php`
- Lógica en `caja3/api/cleanup_fake_data.php`

**Razón**: No se usa. Se usa `site_visits` (11,709 registros) en su lugar

---

### 14. `concurso_matches` (0 registros) ❌
### 15. `concurso_pagos` (0 registros) ❌
### 16. `concurso_participants` (0 registros) ❌
**APIs a eliminar** (todas en `app3/api/`):
- `track_concurso_visit.php` ✅ (usa `concurso_tracking` que SÍ tiene 942 registros)
- `concurso_registro.php` ✅ (usa `concurso_registros` que SÍ tiene 8 registros)
- `get_concurso_live.php` ✅ (usa `concurso_state` que SÍ tiene 1 registro)
- `update_concurso_state.php` ✅
- `tuu_callback_concurso.php` ✅
- `clear_concurso_state.php` ✅
- `tuu_direct_concurso.php` ✅
- `get_concurso_stats.php` ✅
- `delete_concursante.php` ✅
- `get_participantes_concurso.php` ✅
- `process_concurso_payment.php` ✅
- `concurso_pago_callback.php` ✅
- `add_concursante_manual.php` ✅
- `update_concursante.php` ✅
- `get_concurso_live_with_participants.php` ✅

**Razón**: Estas 3 tablas específicas NO se usan. El sistema de concurso usa otras tablas:
- ✅ `concurso_tracking` (942 registros)
- ✅ `concurso_registros` (8 registros)
- ✅ `concurso_state` (1 registro)

**ACCIÓN**: Revisar APIs para confirmar que NO usan estas 3 tablas vacías

---

### 17. `tuu_pagos_online` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/tuu/capture_payment_success.php`
- `app3/api/tuu/save_transaction.php`
- `app3/api/tuu/callback.php` (lógica específica)
- `app3/api/setup_transactions_table.php`
- `app3/api/tuu-pagos-online/setup_table.php`
- `app3/api/tuu-pagos-online/update_payment_status.php`
- `app3/api/tuu-pagos-online/save_transaction.php`
- `app3/api/tuu-pagos-online/get_user_payments.php`

**Razón**: Sistema implementado pero NO usado. Se usa `tuu_orders` (979 registros)

---

### 18. `tuu_payments` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/get_pos_status.php` (lógica específica)

**Razón**: No se usa. Se usa `tuu_pos_transactions` (7 registros)

---

### 19. `tuu_remote_payments` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/tuu/check_payment_status.php`
- `app3/api/tuu/setup_remote_payments_table.php`
- `app3/api/tuu/create_remote_payment.php`

**Razón**: Sistema implementado pero NUNCA usado

---

### 20. `tuu_reports` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/tuu/check_payment_local.php`
- `app3/api/tuu/sync_reports.php`

**Razón**: Sistema de sincronización no usado

---

### 21. `tuu_sync_control` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/tuu/cron_status.php`
- `app3/api/tuu/fix_sync_production.php`
- `app3/api/tuu/setup_cron.php`
- `app3/api/tuu/daily_sync.php`

**Razón**: Sistema de cron/sync no usado

---

### 22. `rl6_credit_audit` (0 registros) ❌
**APIs a eliminar**:
- `app3/api/rl6/refund_credit.php` (lógica específica)

**Razón**: Auditoría no usada. Se usa `rl6_credit_transactions` (40 registros)

---

## 📋 RESUMEN EJECUTIVO

### 🔴 ELIMINAR: 22 tablas vacías
1. `ventas`
2. `customers`
3. `orders`
4. `order_items`
5. `order_extras`
6. `winners`
7. `search_analytics`
8. `user_notifications`
9. `user_coupons`
10. `user_orders`
11. `user_order_items`
12. `cash_register_sessions`
13. `app_visits`
14. `concurso_matches`
15. `concurso_pagos`
16. `concurso_participants`
17. `tuu_pagos_online`
18. `tuu_payments`
19. `tuu_remote_payments`
20. `tuu_reports`
21. `tuu_sync_control`
22. `rl6_credit_audit`

### 📁 APIs a eliminar: ~40 archivos PHP

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
