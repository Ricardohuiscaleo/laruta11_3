# Archivos PHP a Eliminar

## 📁 app3/api/

### Ventas obsoletas
- [ ] `registrar_venta.php`

### Orders obsoletas
- [ ] `get_pending_orders.php`

### Notificaciones (sistema no usado)
- [ ] `notifications/get_notifications.php`
- [ ] `notifications/mark_read.php`
- [ ] `notifications/mark_all_read.php`
- [ ] `notifications/send_notification.php`

### Cupones (sistema no usado)
- [ ] `coupons/get_user_coupons.php`
- [ ] `coupons/use_coupon.php`
- [ ] `coupons/create_coupon.php`

### TUU Pagos Online (no usado)
- [ ] `tuu/capture_payment_success.php`
- [ ] `tuu/save_transaction.php`
- [ ] `tuu/check_payment_status.php`
- [ ] `tuu/setup_remote_payments_table.php`
- [ ] `tuu/create_remote_payment.php`
- [ ] `tuu/check_payment_local.php`
- [ ] `tuu/sync_reports.php`
- [ ] `tuu/cron_status.php`
- [ ] `tuu/fix_sync_production.php`
- [ ] `tuu/setup_cron.php`
- [ ] `tuu/daily_sync.php`
- [ ] `setup_transactions_table.php`
- [ ] `tuu-pagos-online/setup_table.php`
- [ ] `tuu-pagos-online/update_payment_status.php`
- [ ] `tuu-pagos-online/save_transaction.php`
- [ ] `tuu-pagos-online/get_user_payments.php`

### Concurso (revisar si se usan estas APIs)
- [ ] `track_concurso_visit.php` ✅ (usa concurso_tracking - MANTENER)
- [ ] `concurso_registro.php` ✅ (usa concurso_registros - MANTENER)
- [ ] `get_concurso_live.php` ✅ (usa concurso_state - MANTENER)
- [ ] `update_concurso_state.php` ✅ (MANTENER)
- [ ] `tuu_callback_concurso.php` ✅ (MANTENER)
- [ ] `clear_concurso_state.php` ✅ (MANTENER)
- [ ] `tuu_direct_concurso.php` ✅ (MANTENER)
- [ ] `get_concurso_stats.php` ✅ (MANTENER)
- [ ] `delete_concursante.php` ✅ (MANTENER)
- [ ] `get_participantes_concurso.php` ✅ (MANTENER)
- [ ] `process_concurso_payment.php` ✅ (MANTENER)
- [ ] `concurso_pago_callback.php` ✅ (MANTENER)
- [ ] `add_concursante_manual.php` ✅ (MANTENER)
- [ ] `update_concursante.php` ✅ (MANTENER)
- [ ] `get_concurso_live_with_participants.php` ✅ (MANTENER)

---

## 📁 caja3/api/

### Orders obsoletas
- [ ] `get_pending_orders.php`
- [ ] Lógica en `registrar_cumpleanos.php` (actualizar para usar tuu_orders)

### Notificaciones (sistema no usado)
- [ ] `notifications/get_notifications.php`
- [ ] `notifications/mark_read.php`
- [ ] `notifications/mark_all_read.php`

### Cash Register Sessions (no usado)
- [ ] `get_cash_register_status.php`
- [ ] `open_cash_register.php`
- [ ] `close_cash_register.php`
- [ ] `setup_cash_register_table.php`

---

## 🔧 Archivos a MODIFICAR (no eliminar)

### app3/api/
- [ ] `cleanup_fake_data.php` - Remover lógica de: ventas, app_visits
- [ ] `tracker/update_kanban_status.php` - Remover lógica de: user_notifications
- [ ] `tuu/callback.php` - Remover lógica de: tuu_pagos_online
- [ ] `get_pos_status.php` - Remover lógica de: tuu_payments
- [ ] `rl6/refund_credit.php` - Remover lógica de: rl6_credit_audit
- [ ] `app/get_analytics.php` - Remover lógica de: user_orders
- [ ] `users/get_user_detail.php` - Remover lógica de: user_orders, user_order_items
- [ ] `get_analytics.php` - Remover lógica de: user_orders
- [ ] `get_user_detail.php` - Remover lógica de: user_orders, user_order_items

### caja3/api/
- [ ] `cleanup_fake_data.php` - Remover lógica de: ventas, app_visits
- [ ] `app/get_analytics.php` - Remover lógica de: user_orders
- [ ] `users/get_user_detail.php` - Remover lógica de: user_orders, user_order_items
- [ ] `get_analytics.php` - Remover lógica de: user_orders
- [ ] `get_user_detail.php` - Remover lógica de: user_orders, user_order_items

---

## 📊 Resumen

- **Archivos a eliminar completamente**: ~25 archivos
- **Archivos a modificar**: ~14 archivos
- **Total de cambios**: ~39 archivos

---

## ⚠️ IMPORTANTE

Antes de eliminar:
1. ✅ Hacer backup de la base de datos
2. ✅ Hacer backup del código
3. ✅ Probar en ambiente de desarrollo primero
4. ✅ Verificar que las APIs de concurso NO usan las tablas vacías
