# Tasks: Fix Inventario, Ventas y Comandas

## Task 1: Guard de idempotencia en processSaleInventory
- [x] 1.1 Agregar guard de duplicados al inicio de `processSaleInventory()` en `app3/api/process_sale_inventory_fn.php`: si ya existen `inventory_transactions` para el `order_reference`, retornar `['success' => true, 'skipped' => true]` sin iniciar transacción
- [ ] 1.2 Deploy y verificar en SSH:
  ```
  # Buscar una orden que YA tiene inventory_transactions
  mysql -e "SELECT order_reference FROM inventory_transactions GROUP BY order_reference LIMIT 1;"
  # Verificar que el guard funciona revisando logs después de un callback duplicado
  ```

## Task 2: Fix order_status en create_payment_direct.php (R11 Webpay)
- [x] 2.1 En `app3/api/tuu/create_payment_direct.php`, cambiar `order_status` de `'pending'` a `'sent_to_kitchen'` en el INSERT SQL
- [x] 2.2 Agregar cálculo server-side de subtotal: iterar cart_items, buscar precio real en tabla products, calcular SUM(db_price * quantity) + customizations
- [x] 2.3 Agregar cálculo server-side de delivery_fee: pickup=0, delivery=base_fee + surcharge, fallback al valor del cliente si geocoding falla
- [x] 2.4 Recalcular total como subtotal + delivery_fee + delivery_extras - discount_amount - delivery_discount - cashback_used
- [ ] 2.5 Deploy y verificar en SSH:
  ```
  mysql -e "SELECT order_number, order_status, payment_status, subtotal, delivery_fee, product_price FROM tuu_orders WHERE order_number LIKE 'R11-%' ORDER BY created_at DESC LIMIT 3;"
  # Verificar order_status='sent_to_kitchen' y subtotal > 0
  # Verificar en comandas (caja.laruta11.cl) que la orden R11 aparece
  ```

## Task 3: Fix callback_simple.php (preservar order_status + inventario)
- [x] 3.1 Modificar UPDATE para pago aprobado: solo actualizar status='completed' y payment_status='paid' SIN tocar order_status
- [x] 3.2 Agregar manejo de pago fallido/cancelado: SET order_status='cancelled'
- [x] 3.3 Agregar guard de duplicados antes de llamar processSaleInventory()
- [ ] 3.4 Deploy y verificar en SSH:
  ```
  mysql -e "SELECT order_number, order_status, payment_status FROM tuu_orders WHERE order_number LIKE 'R11-%' AND payment_status='paid' ORDER BY created_at DESC LIMIT 3;"
  # Verificar order_status='sent_to_kitchen' (NO 'pending' ni 'delivered')
  mysql -e "SELECT * FROM inventory_transactions WHERE order_reference LIKE 'R11-%' ORDER BY id DESC LIMIT 10;"
  ```

## Task 4: Fix callback.php (no cambiar order_status a delivered)
- [x] 4.1 Para result=completed: eliminar order_status del UPDATE, solo actualizar status y payment_status
- [x] 4.2 Para result failed/cancelled: SET order_status='cancelled', payment_status='unpaid'
- [x] 4.3 Agregar guard de duplicados antes del bloque de inventario
- [ ] 4.4 Deploy y verificar en SSH:
  ```
  mysql -e "SELECT order_number, order_status FROM tuu_orders WHERE order_number LIKE 'R11-%' AND order_status='delivered' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);"
  # Debe retornar 0 filas después del fix
  ```

## Task 5: Refactorizar inventario en create_order.php (R11C + validación fees)
- [x] 5.1 Agregar require_once de process_sale_inventory_fn.php y función helper buildInventoryItems()
- [x] 5.2 Reemplazar bloque inline de inventario (~80 líneas) por llamada a processSaleInventory() — llamar DESPUÉS del commit de la transacción principal
- [x] 5.3 Agregar cálculo server-side de subtotal y delivery_fee (misma lógica que task 2)
- [x] 5.4 Recalcular total y usar en product_price e installment_amount
- [ ] 5.5 Deploy y verificar en SSH:
  ```
  mysql -e "SELECT order_number, subtotal, delivery_fee FROM tuu_orders WHERE order_number LIKE 'R11C-%' ORDER BY created_at DESC LIMIT 3;"
  mysql -e "SELECT it.order_reference, COUNT(*) as txs FROM inventory_transactions it WHERE it.order_reference LIKE 'R11C-%' GROUP BY it.order_reference ORDER BY it.order_reference DESC LIMIT 5;"
  ```

## Task 6: Expandir backfill para todos los prefijos
- [x] 6.1 Cambiar WHERE de backfill_r11_inventory.php para cubrir R11-%, R11C-%, T11-%
- [ ] 6.2 Deploy y verificar en SSH:
  ```
  mysql -e "SELECT SUBSTRING_INDEX(o.order_number, '-', 1) as prefix, COUNT(*) as missing FROM tuu_orders o WHERE o.payment_status='paid' AND o.order_status NOT IN ('cancelled','failed') AND NOT EXISTS (SELECT 1 FROM inventory_transactions it WHERE it.order_reference = o.order_number) GROUP BY prefix;"
  ```

## Task 7: Ejecutar backfill de órdenes históricas
- [ ] 7.1 Ejecutar backfill script en producción vía SSH y capturar output
- [ ] 7.2 Verificar en SSH:
  ```
  mysql -e "SELECT COUNT(*) as missing FROM tuu_orders o WHERE o.payment_status='paid' AND o.order_status NOT IN ('cancelled','failed') AND NOT EXISTS (SELECT 1 FROM inventory_transactions it WHERE it.order_reference = o.order_number);"
  # Debe retornar 0 o muy pocos
  ```

## Task 8: Test end-to-end de todos los flujos
- [ ] 8.1 Test Webpay: crear orden, verificar sent_to_kitchen, comandas, inventario post-pago
- [ ] 8.2 Test R11C: crear orden crédito, verificar inventario al crear, subtotal/delivery_fee correctos
- [ ] 8.3 Test T11 regresión: verificar que register_success.php sigue funcionando
- [ ] 8.4 Verificación final en SSH:
  ```
  mysql -e "
  SELECT 'Órdenes sin inventario (24h)' as metric, COUNT(*) as value
  FROM tuu_orders o WHERE o.payment_status='paid' AND o.order_status NOT IN ('cancelled','failed')
    AND o.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND NOT EXISTS (SELECT 1 FROM inventory_transactions it WHERE it.order_reference = o.order_number)
  UNION ALL
  SELECT 'Órdenes en comandas (24h)', COUNT(*) FROM tuu_orders 
  WHERE order_status NOT IN ('delivered','cancelled') AND order_number NOT LIKE 'RL6-%'
    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
  UNION ALL
  SELECT 'Posible doble descuento', COUNT(*) FROM (
    SELECT order_reference FROM inventory_transactions 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY order_reference HAVING COUNT(*) > 50) t;"
  ```
