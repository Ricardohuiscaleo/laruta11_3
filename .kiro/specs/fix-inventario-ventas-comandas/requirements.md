# Requirements Document

## Introduction

Este documento especifica los requisitos para corregir cinco bugs críticos en producción que afectan el tracking de inventario, el registro de ventas y el sistema de comandas de cocina de La Ruta 11. Los bugs causan que órdenes Webpay (R11-*) y Crédito R11 (R11C-*) no descuenten inventario, no aparezcan en comandas, registren delivery_fee y subtotal incorrectos, y que existan órdenes históricas sin reconciliar.

## Glossary

- **Order_Creator**: Módulo PHP que crea órdenes en la tabla `tuu_orders` y sus items en `tuu_order_items`. Existen dos instancias: `app3/api/create_order.php` (app cliente) y `caja3/api/create_order.php` (POS/caja).
- **Webpay_Payment_Creator**: Módulo PHP (`app3/api/tuu/create_payment_direct.php`) que crea órdenes R11-* con estado `pending`/`unpaid` y redirige al gateway Webpay de TUU.
- **Payment_Callback**: Módulo PHP (`app3/api/tuu/callback_simple.php` y `callback.php`) que recibe la confirmación de pago de TUU y actualiza el estado de la orden.
- **Inventory_Processor**: Módulo PHP (`app3/api/process_sale_inventory_fn.php`) que descuenta ingredientes del stock según las recetas de los productos vendidos, registrando cada movimiento en `inventory_transactions`.
- **Comandas_API**: Endpoint PHP (`caja3/api/tuu/get_comandas_v2.php`) que consulta órdenes activas para mostrar en la pantalla de cocina, filtrando por `order_status NOT IN ('delivered', 'cancelled')`.
- **Delivery_Fee_Calculator**: Módulo PHP (`app3/api/location/get_delivery_fee.php`) que calcula la tarifa de delivery basándose en la distancia Google Directions entre el food truck activo y la dirección del cliente.
- **Backfill_Script**: Script PHP (`app3/api/backfill_r11_inventory.php`) que reconcilia órdenes históricas R11-* pagadas que no tienen transacciones de inventario asociadas.
- **R11_Order**: Orden creada desde app3 vía Webpay con prefijo `R11-`.
- **R11C_Order**: Orden creada desde app3 con pago Crédito R11 con prefijo `R11C-`.
- **T11_Order**: Orden creada desde caja3 vía terminal Tuu con prefijo `T11-`.
- **Composite_Ingredient**: Ingrediente que es una sub-receta compuesta por otros ingredientes base, expandido recursivamente al descontar stock.

## Requirements

### Requirement 1: Descuento de inventario unificado para todas las órdenes pagadas

**User Story:** Como administrador del restaurante, quiero que todas las órdenes pagadas (Webpay, Crédito R11, Tuu, efectivo) descuenten inventario de forma consistente, para que el stock refleje la realidad operativa.

#### Acceptance Criteria

1. WHEN a Payment_Callback receives a successful payment confirmation for an R11_Order, THE Inventory_Processor SHALL deduct ingredient stock from `inventory_transactions` and update `ingredients.current_stock` for each product in the order within the same database transaction.
2. WHEN the Payment_Callback receives a successful payment confirmation for an R11_Order that contains combo items, THE Inventory_Processor SHALL expand fixed_items and selections from `combo_data` and deduct stock for each component product.
3. WHEN the Payment_Callback receives a successful payment confirmation for an R11_Order that contains products with customizations, THE Inventory_Processor SHALL deduct stock for each customization product in addition to the base product.
4. WHEN the Order_Creator creates an R11C_Order with `payment_method = 'r11_credit'`, THE Inventory_Processor SHALL deduct ingredient stock at order creation time, because R11C_Orders are marked as `paid` immediately.
5. WHEN the Inventory_Processor deducts stock for a product that has a recipe with ingredients using unit `g`, THE Inventory_Processor SHALL convert grams to kilograms by dividing by 1000 before deducting from `ingredients.current_stock`.
6. IF the Inventory_Processor encounters a product_id that does not exist in the `products` table, THEN THE Inventory_Processor SHALL skip that product and log a warning without aborting the transaction.
7. IF the Inventory_Processor has already recorded `inventory_transactions` for a given `order_reference`, THEN THE Inventory_Processor SHALL skip duplicate processing to prevent double-deduction.
8. THE Inventory_Processor SHALL recalculate `products.stock_quantity` as `FLOOR(MIN(current_stock / recipe_quantity))` across all active ingredients after deducting stock for each product.

### Requirement 2: Órdenes Webpay visibles en comandas de cocina

**User Story:** Como cocinero, quiero ver los pedidos Webpay (R11-*) en la pantalla de comandas de cocina, para poder prepararlos sin depender de notificaciones manuales.

#### Acceptance Criteria

1. WHEN the Webpay_Payment_Creator creates an R11_Order in `tuu_orders`, THE Webpay_Payment_Creator SHALL set `order_status = 'sent_to_kitchen'` so that the order is visible in comandas immediately.
2. WHEN the Comandas_API queries active orders, THE Comandas_API SHALL return all orders with `order_status NOT IN ('delivered', 'cancelled')` regardless of the `order_number` prefix (R11-, R11C-, T11-).
3. WHEN the Payment_Callback updates an R11_Order after successful Webpay payment, THE Payment_Callback SHALL set `payment_status = 'paid'` and preserve `order_status = 'sent_to_kitchen'` instead of changing it to `'delivered'` or `'pending'`.
4. IF the Payment_Callback receives a failed or cancelled payment for an R11_Order, THEN THE Payment_Callback SHALL set `order_status = 'cancelled'` so the order is removed from comandas.

### Requirement 3: Recálculo server-side de delivery_fee

**User Story:** Como administrador del restaurante, quiero que el delivery_fee sea recalculado en el servidor al crear la orden, para evitar manipulación o errores del cliente.

#### Acceptance Criteria

1. WHEN the Order_Creator or Webpay_Payment_Creator receives an order with `delivery_type = 'delivery'` and a `delivery_address`, THE Order_Creator SHALL recalculate the delivery_fee server-side using the Delivery_Fee_Calculator logic (base fee from `food_trucks.tarifa_delivery` + surcharge of $1000 per 2km beyond 6km).
2. WHEN the server-side calculated delivery_fee differs from the client-provided delivery_fee, THE Order_Creator SHALL use the server-side calculated value and store it in `tuu_orders.delivery_fee`.
3. WHEN the Order_Creator recalculates the delivery_fee, THE Order_Creator SHALL also recalculate the total amount as `subtotal + delivery_fee + delivery_extras - discount_amount - delivery_discount - cashback_used`.
4. IF the Delivery_Fee_Calculator cannot geocode the delivery address or cannot reach the Google Directions API, THEN THE Order_Creator SHALL fall back to the Haversine distance formula for fee calculation.
5. WHEN the order has `delivery_type = 'pickup'`, THE Order_Creator SHALL set `delivery_fee = 0` regardless of the client-provided value.

### Requirement 4: Cálculo correcto de subtotal para órdenes Webpay

**User Story:** Como administrador del restaurante, quiero que el subtotal de las órdenes Webpay se calcule correctamente en el servidor, para que los reportes de ventas sean precisos.

#### Acceptance Criteria

1. WHEN the Webpay_Payment_Creator creates an R11_Order with cart_items, THE Webpay_Payment_Creator SHALL calculate the subtotal server-side as the sum of `(product_price * quantity)` for each item, plus the sum of `(customization_price * customization_quantity)` for each customization.
2. WHEN the Webpay_Payment_Creator stores the order in `tuu_orders`, THE Webpay_Payment_Creator SHALL populate the `subtotal` field with the server-calculated value instead of relying on the client-provided `subtotal` field.
3. WHEN the Webpay_Payment_Creator stores order items in `tuu_order_items`, THE Webpay_Payment_Creator SHALL populate the `subtotal` field of each item as `product_price * quantity` plus the total price of customizations for that item.
4. THE Webpay_Payment_Creator SHALL validate that `product_price` for each cart item matches the price in the `products` table, and use the database price if there is a discrepancy.

### Requirement 5: Backfill de inventario para órdenes históricas

**User Story:** Como administrador del restaurante, quiero reconciliar el inventario de órdenes históricas que no descontaron stock, para que el inventario actual refleje todas las ventas realizadas.

#### Acceptance Criteria

1. WHEN the Backfill_Script is executed, THE Backfill_Script SHALL identify all orders in `tuu_orders` with `payment_status = 'paid'` and `order_status NOT IN ('cancelled', 'failed')` that have no corresponding records in `inventory_transactions` for their `order_number`.
2. WHEN the Backfill_Script processes an order, THE Backfill_Script SHALL query `tuu_order_items` using `order_reference` to obtain the product list, and call the Inventory_Processor for each item.
3. WHEN the Backfill_Script processes a combo item (item_type = 'combo'), THE Backfill_Script SHALL parse `combo_data` JSON to extract `fixed_items` and `selections`, and deduct stock for each component product.
4. WHEN the Backfill_Script processes a product with customizations stored in `combo_data`, THE Backfill_Script SHALL extract and deduct stock for each customization product.
5. THE Backfill_Script SHALL cover all order prefixes (R11-*, R11C-*, T11-*) that are missing inventory transactions, not only R11-* orders.
6. THE Backfill_Script SHALL process each order in its own database transaction, so that a failure in one order does not prevent processing of subsequent orders.
7. THE Backfill_Script SHALL log the result (success or error with message) for each processed order and output a final summary with total orders processed, successes, and failures.
