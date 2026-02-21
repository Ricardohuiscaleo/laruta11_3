# La Ruta 11 - Database Schema

## 📊 Resumen
Base de datos MySQL compartida entre app3 (clientes) y caja3 (cajeros) con 80+ tablas.

## 🗄️ Tablas Principales

### Usuarios y Autenticación

#### `usuarios` - Clientes del sistema
- `id` (PK, auto_increment)
- `google_id` (varchar 255, indexed) - OAuth Google
- `nombre` (varchar 255, NOT NULL)
- `email` (varchar 255, UNIQUE)
- `password` (varchar 255) - Hash
- `telefono` (varchar 20)
- `foto_perfil` (text) - URL
- `fecha_nacimiento` (date)
- `genero` (enum: masculino, femenino, otro, no_decir)
- `direccion` (varchar 500)
- `latitud`, `longitud` (decimal)
- `total_orders` (int, default 0)
- `total_spent` (decimal 10,2, default 0.00)
- `session_token` (varchar 64)
- `fecha_registro` (timestamp, default CURRENT_TIMESTAMP)
- `ultimo_acceso` (timestamp, auto-update)
- `activo` (tinyint 1, default 1)

**RL6 Credit Fields:**
- `es_militar_rl6` (tinyint 1, default 0, indexed)
- `rut` (varchar 12, indexed)
- `grado_militar` (varchar 50)
- `unidad_trabajo` (varchar 200)
- `domicilio_particular` (text)
- `carnet_frontal_url`, `carnet_trasero_url`, `selfie_url` (varchar 500)
- `credito_aprobado` (tinyint 1, default 0)
- `limite_credito` (decimal 10,2, default 0.00)
- `credito_usado` (decimal 10,2, default 0.00)
- `credito_bloqueado` (tinyint 1, default 0, indexed)
- `fecha_solicitud_rl6`, `fecha_aprobacion_rl6` (timestamp)
- `fecha_ultimo_pago` (date, indexed)

#### `cashiers` - Cajeros del sistema
- `id` (PK, auto_increment)
- `username` (varchar 50, UNIQUE, NOT NULL)
- `password` (varchar 255, NOT NULL) - Hash
- `full_name` (varchar 100, NOT NULL)
- `phone` (varchar 20)
- `email` (varchar 100)
- `role` (enum: cajero, admin, default cajero)
- `active` (tinyint 1, default 1)
- `created_at`, `updated_at` (timestamp)

#### `admin_users` - Administradores
- `id` (PK, auto_increment)
- `username` (varchar 50, UNIQUE, NOT NULL)
- `email` (varchar 150, UNIQUE, NOT NULL)
- `password_hash` (varchar 255, NOT NULL)
- `full_name` (varchar 150, NOT NULL)
- `role` (enum: admin, manager, cashier, default cashier, indexed)
- `is_active` (tinyint 1, default 1)
- `created_at` (timestamp)

### Productos y Menú

#### `products` - Productos del menú
- `id` (PK, auto_increment)
- `category_id` (int, NOT NULL, indexed, FK → categories)
- `subcategory_id` (int, indexed, FK → subcategories)
- `name` (varchar 150, NOT NULL)
- `description` (text)
- `price` (decimal 10,2, NOT NULL)
- `cost_price` (decimal 10,2, default 0.00)
- `image_url` (text)
- `sku` (varchar 50, UNIQUE) - Código interno
- `barcode` (varchar 100, NULL) - Código de barras/QR
- `stock_quantity` (int, default 0, indexed)
- `min_stock_level` (int, default 5)
- `is_active` (tinyint 1, default 1, indexed)
- `has_variants` (tinyint 1, default 0)
- `preparation_time` (int, default 10) - minutos
- `calories` (int)
- `allergens` (text)
- `grams` (int, default 0)
- `views`, `likes` (int, default 0)
- `created_at`, `updated_at` (timestamp)

#### `categories` - Categorías principales
- `id` (PK, auto_increment)
- `name` (varchar 100, NOT NULL)
- `description` (text)
- `image_url` (varchar 255)
- `sort_order` (int, default 0, indexed)
- `is_active` (tinyint 1, default 1, indexed)
- `created_at`, `updated_at` (timestamp)

**Categorías en producción:**
- ID 2: Churrascos
- ID 3: Hamburguesas (subcategory_id 5: 100g, 6: normal)
- ID 4: Completos
- ID 5: Pizzas (subcategory_id 60)
- ID 8: Combos
- ID 12: Papas (subcategory_id 57)

#### `subcategories` - Subcategorías
- `id` (PK, auto_increment)
- `category_id` (int, NOT NULL, indexed, FK → categories)
- `name` (varchar 100, NOT NULL)
- `slug` (varchar 100, NOT NULL)
- `description` (text)
- `sort_order` (int, default 0)
- `is_active` (tinyint 1, default 1)
- `created_at`, `updated_at` (timestamp)

#### `combos` - Combos de productos
- `id` (PK, auto_increment)
- `name` (varchar 255, NOT NULL)
- `description` (text)
- `price` (decimal 10,2, NOT NULL)
- `image_url` (varchar 500)
- `category_id` (int, default 8, indexed, FK → categories)
- `active` (tinyint 1, default 1)
- `created_at` (timestamp)

#### `combo_items` - Items incluidos en combos
- `id` (PK, auto_increment)
- `combo_id` (int, NOT NULL, indexed, FK → combos)
- `product_id` (int, NOT NULL, indexed, FK → products)
- `quantity` (int, default 1)
- `is_selectable` (tinyint 1, default 0)
- `selection_group` (varchar 50)

### Inventario

#### `ingredients` - Ingredientes
- `id` (PK, auto_increment)
- `name` (varchar 100, NOT NULL, indexed)
- `category` (varchar 50)
- `unit` (varchar 20, NOT NULL) - kg, g, L, ml, unidad
- `cost_per_unit` (decimal 10,2, NOT NULL)
- `current_stock` (decimal 10,2, default 0.00, indexed)
- `min_stock_level` (decimal 10,2, default 1.00)
- `supplier` (varchar 100)
- `barcode` (varchar 100, NULL) - Código de barras/QR
- `internal_code` (varchar 100, NULL) - Código interno proveedor (SAP, etc.)
- `expiry_date` (date, indexed)
- `is_active` (tinyint 1, default 1)
- `created_at`, `updated_at` (timestamp)

#### `product_recipes` - Recetas de productos
- `id` (PK, auto_increment)
- `product_id` (int, NOT NULL, indexed, FK → products)
- `ingredient_id` (int, NOT NULL, indexed, FK → ingredients)
- `quantity` (decimal 10,3, NOT NULL)
- `unit` (varchar 20, NOT NULL)
- `created_at` (timestamp)

#### `inventory_transactions` - Transacciones de inventario
- `id` (PK, auto_increment)
- `transaction_type` (enum: sale, purchase, adjustment, return, indexed)
- `ingredient_id` (int, indexed, FK → ingredients)
- `product_id` (int, indexed, FK → products)
- `quantity` (decimal 10,3, NOT NULL)
- `unit` (varchar 10)
- `previous_stock`, `new_stock` (decimal 10,3)
- `order_reference` (varchar 100, indexed)
- `order_item_id` (int, indexed)
- `notes` (text)
- `created_by` (int) - FK → cashiers
- `created_at` (timestamp, indexed)

#### `mermas` - Pérdidas/desperdicios
- `id` (PK, auto_increment)
- `ingredient_id` (int, indexed, FK → ingredients)
- `product_id` (int, FK → products)
- `item_type` (enum: ingredient, product, default ingredient)
- `item_name` (varchar 150)
- `quantity` (decimal 10,3, NOT NULL)
- `unit` (varchar 20, NOT NULL)
- `cost` (decimal 10,2, NOT NULL)
- `reason` (varchar 255)
- `user_id` (int) - FK → cashiers
- `created_at` (timestamp)

### Compras

#### `compras` - Órdenes de compra
- `id` (PK, auto_increment)
- `fecha_compra` (datetime, NOT NULL, indexed)
- `proveedor` (varchar 255)
- `tipo_compra` (enum: ingredientes, insumos, equipamiento, otros, default ingredientes)
- `monto_total` (decimal 12,2, NOT NULL)
- `metodo_pago` (enum: cash, transfer, card, credit, default cash)
- `estado` (enum: pendiente, pagado, cancelado, default pagado, indexed)
- `notas` (text)
- `imagen_respaldo` (varchar 500) - URL boleta/factura
- `usuario` (varchar 100)
- `created_at`, `updated_at` (timestamp)

#### `compras_detalle` - Detalle de compras
- `id` (PK, auto_increment)
- `compra_id` (int, NOT NULL, indexed, FK → compras)
- `ingrediente_id` (int, indexed, FK → ingredients)
- `product_id` (int, indexed, FK → products)
- `item_type` (enum: ingredient, product, default ingredient)
- `nombre_item` (varchar 255, NOT NULL)
- `cantidad` (decimal 10,2, NOT NULL)
- `unidad` (varchar 50)
- `precio_unitario` (decimal 10,2, NOT NULL)
- `subtotal` (decimal 10,2, NOT NULL)
- `stock_antes`, `stock_despues` (decimal 10,2)

### Órdenes y Ventas

#### `tuu_orders` - Órdenes principales
- `id` (PK, auto_increment)
- `order_number` (varchar 50, UNIQUE, NOT NULL)
- `user_id` (int, indexed, FK → usuarios)
- `customer_name` (varchar 255, NOT NULL)
- `customer_phone` (varchar 20)
- `table_number` (varchar 10)
- `product_name` (varchar 255, NOT NULL) - Resumen
- `has_item_details` (tinyint 1, default 0)
- `product_price` (decimal 10,2, NOT NULL)

**Payment Fields:**
- `payment_method` (enum: webpay, transfer, card, cash, pedidosya, rl6_credit, default webpay)
- `payment_status` (enum: unpaid, pending_payment, paid, failed, default unpaid)
- `pagado_con_credito_rl6` (tinyint 1, default 0, indexed)
- `monto_credito_rl6` (decimal 10,2, default 0.00)

**TUU Integration:**
- `tuu_payment_request_id` (int, indexed)
- `tuu_idempotency_key` (varchar 255)
- `tuu_device_used` (varchar 50)
- `tuu_transaction_id` (varchar 100)
- `tuu_amount` (decimal 10,2)
- `tuu_timestamp` (varchar 255)
- `tuu_message` (varchar 255)
- `tuu_account_id` (varchar 50)
- `tuu_currency` (varchar 10)
- `tuu_signature` (text)

**Order Details:**
- `order_status` (enum: pending, sent_to_kitchen, preparing, ready, out_for_delivery, delivered, completed, cancelled, default pending)
- `status` (enum: pending, sent_to_pos, completed, failed, default pending, indexed)
- `delivery_type` (enum: pickup, delivery, cuartel, default pickup)
- `delivery_address` (text)
- `delivery_fee` (decimal 10,2, default 0.00, indexed)
- `pickup_time` (time)
- `scheduled_time` (datetime)
- `is_scheduled` (tinyint 1, default 0)
- `customer_notes` (text)
- `special_instructions` (text)

**Pricing:**
- `subtotal` (decimal 10,2, default 0.00)
- `discount_amount` (decimal 10,2, default 0.00)
- `discount_10` (decimal 10,2, default 0.00) - Descuento retiro
- `discount_30` (decimal 10,2, default 0.00)
- `discount_birthday` (decimal 10,2, default 0.00)
- `discount_pizza` (decimal 10,2, default 0.00)
- `delivery_discount` (decimal 10,2, default 0.00)
- `delivery_extras` (decimal 10,2, default 0.00)
- `delivery_extras_items` (JSON)
- `cashback_used` (decimal 10,2, default 0.00)

**Installments:**
- `installments_total` (int, default 1)
- `installment_current` (int, default 1)
- `installment_amount` (decimal 10,2, NOT NULL)

**Staff:**
- `cashier_id` (int, indexed, FK → cashiers)
- `cashier_name` (varchar 100)
- `rider_id` (int)
- `estimated_delivery_time` (timestamp)

**Rewards:**
- `reward_used` (varchar 50)
- `reward_stamps_consumed` (int, default 0)
- `reward_applied_at` (timestamp)

- `created_at` (timestamp, indexed)
- `updated_at` (timestamp)

#### `tuu_order_items` - Items de órdenes
- `id` (PK, auto_increment)
- `order_id` (int, NOT NULL, indexed, FK → tuu_orders)
- `order_reference` (varchar 100, NOT NULL, indexed)
- `product_id` (int, indexed, FK → products)
- `item_type` (enum: product, personalizar, extras, acompañamiento, combo, default product)
- `combo_data` (JSON)
- `product_name` (varchar 255, NOT NULL)
- `product_price` (decimal 10,2, NOT NULL)
- `item_cost` (decimal 10,2, default 0.00)
- `quantity` (int, default 1, NOT NULL)
- `subtotal` (decimal 10,2, NOT NULL)
- `created_at` (timestamp)

### Crédito RL6

#### `rl6_credit_transactions` - Transacciones de crédito
- `id` (PK, auto_increment)
- `user_id` (int, NOT NULL, indexed, FK → usuarios)
- `amount` (decimal 10,2, NOT NULL)
- `type` (enum: credit, debit, refund, NOT NULL)
  - `debit`: Compra con crédito (resta)
  - `refund`: Reembolso/pago (suma)
  - `credit`: Ajuste manual
- `description` (varchar 255)
- `order_id` (varchar 50) - Referencia a order_number
- `created_at` (timestamp, indexed)

#### `email_logs` - Registro de emails enviados
- `id` (PK, auto_increment)
- `user_id` (int, NOT NULL, indexed, FK → usuarios)
- `email_to` (varchar 255, NOT NULL)
- `email_type` (enum: payment_confirmation, credit_statement, payment_reminder, credit_blocked, indexed)
- `subject` (varchar 500, NOT NULL)
- `order_id` (varchar 50, indexed)
- `amount` (decimal 10,2)
- `gmail_message_id` (varchar 255)
- `gmail_thread_id` (varchar 255)
- `status` (enum: sent, failed, default sent)
- `error_message` (text)
- `sent_at` (timestamp, indexed)

#### `gmail_tokens` - Tokens OAuth Gmail
- `id` (PK, auto_increment)
- `access_token` (text, NOT NULL)
- `refresh_token` (text, NOT NULL)
- `expires_at` (int, NOT NULL) - Unix timestamp
- `created_at`, `updated_at` (timestamp)

### Caja y Finanzas

#### `caja_movimientos` - Movimientos de caja
- `id` (PK, auto_increment)
- `tipo` (enum: ingreso, retiro, NOT NULL, indexed)
- `monto` (decimal 10,2, NOT NULL)
- `motivo` (varchar 500, NOT NULL)
- `saldo_anterior` (decimal 10,2, NOT NULL)
- `saldo_nuevo` (decimal 10,2, NOT NULL)
- `usuario` (varchar 100, default Sistema)
- `order_reference` (varchar 50, indexed)
- `fecha_movimiento` (timestamp, indexed)

#### `capital_trabajo` - Capital de trabajo diario
- `id` (PK, auto_increment)
- `fecha` (date, UNIQUE, NOT NULL)
- `saldo_inicial` (decimal 12,2, default 0.00, NOT NULL)
- `ingresos_ventas` (decimal 12,2, default 0.00, NOT NULL)
- `egresos_compras` (decimal 12,2, default 0.00, NOT NULL)
- `egresos_gastos` (decimal 12,2, default 0.00, NOT NULL)
- `saldo_final` (decimal 12,2, default 0.00, NOT NULL)
- `notas` (text)
- `created_at`, `updated_at` (timestamp)

### Wallet y Recompensas

#### `user_wallet` - Billetera de usuario
- `id` (PK, auto_increment)
- `user_id` (int, UNIQUE, NOT NULL, FK → usuarios)
- `balance` (decimal 10,2, default 0.00)
- `total_earned` (decimal 10,2, default 0.00)
- `total_used` (decimal 10,2, default 0.00)
- `updated_at` (timestamp)

#### `wallet_transactions` - Transacciones de wallet
- `id` (PK, auto_increment)
- `user_id` (int, NOT NULL, indexed, FK → usuarios)
- `type` (enum: earned, used, NOT NULL)
- `amount` (decimal 10,2, NOT NULL)
- `order_id` (varchar 50)
- `description` (text)
- `balance_before`, `balance_after` (decimal 10,2)
- `created_at` (timestamp)

### Notificaciones

#### `order_notifications` - Notificaciones de órdenes
- `id` (PK, auto_increment)
- `order_id` (int, NOT NULL, indexed, FK → tuu_orders)
- `order_number` (varchar 50, NOT NULL)
- `customer_name` (varchar 100)
- `customer_phone` (varchar 20)
- `status` (varchar 50, NOT NULL)
- `message` (text, NOT NULL)
- `is_read` (tinyint 1, default 0)
- `created_at` (timestamp, indexed)

### Analytics y Tracking

#### `site_visits` - Visitas al sitio
- `id` (PK, auto_increment)
- `ip_address` (varchar 45, NOT NULL, indexed)
- `user_agent` (text)
- `page_url` (varchar 500, NOT NULL)
- `referrer` (varchar 500)
- `session_id` (varchar 100, indexed)
- `visit_date` (date, NOT NULL, indexed)
- `visit_time` (timestamp)
- `country`, `city` (varchar 100)
- `device_type` (enum: mobile, tablet, desktop, default mobile)
- `browser` (varchar 100)
- `latitude`, `longitude` (decimal)
- `screen_resolution`, `viewport_size` (varchar 20)
- `timezone` (varchar 50)
- `language` (varchar 10)
- `platform` (varchar 50)
- `full_address` (text)
- `created_at` (timestamp)

#### `user_interactions` - Interacciones de usuario
- `id` (PK, auto_increment)
- `session_id` (varchar 100, indexed)
- `user_ip` (varchar 45)
- `action_type` (enum: click, view, hover, scroll, search, add_to_cart, remove_from_cart, indexed)
- `element_type` (varchar 50)
- `element_id` (varchar 100)
- `element_text` (text)
- `product_id` (int, indexed, FK → products)
- `category_id` (int)
- `page_url` (varchar 500)
- `timestamp` (timestamp)

#### `product_analytics` - Analytics de productos
- `id` (PK, auto_increment)
- `product_id` (int, indexed, FK → products)
- `product_name` (varchar 200)
- `views_count` (int, default 0)
- `clicks_count` (int, default 0)
- `cart_adds` (int, default 0)
- `cart_removes` (int, default 0)
- `purchase_count` (int, default 0)
- `last_interaction` (timestamp)

#### `daily_metrics` - Métricas diarias
- `id` (PK, auto_increment)
- `metric_date` (date, UNIQUE, NOT NULL)
- `unique_visitors` (int, default 0)
- `total_visits` (int, default 0)
- `new_users` (int, default 0)
- `total_orders` (int, default 0)
- `total_revenue` (decimal 10,2, default 0.00)
- `avg_order_value` (decimal 8,2, default 0.00)
- `created_at`, `updated_at` (timestamp)

### Reviews y Feedback

#### `reviews` - Reseñas de productos
- `id` (PK, auto_increment)
- `product_id` (int, NOT NULL, indexed, FK → products)
- `user_id` (int, indexed, FK → usuarios)
- `customer_name` (varchar 100, NOT NULL)
- `rating` (int, NOT NULL, indexed) - 1-5
- `comment` (text)
- `ip_address` (varchar 45)
- `is_approved` (tinyint 1, default 1)
- `created_at` (timestamp, indexed)

### Checklists y Calidad

#### `checklists` - Checklists operacionales
- `id` (PK, auto_increment)
- `type` (enum: apertura, cierre, NOT NULL)
- `scheduled_time` (time, NOT NULL)
- `scheduled_date` (date, NOT NULL, indexed)
- `started_at`, `completed_at` (datetime)
- `status` (enum: pending, active, completed, missed, default pending, indexed)
- `user_id` (int) - FK → cashiers
- `user_name` (varchar 255)
- `total_items`, `completed_items` (int, NOT NULL)
- `completion_percentage` (decimal 5,2, default 0.00)
- `notes` (text)
- `created_at`, `updated_at` (timestamp)

#### `checklist_items` - Items de checklist
- `id` (PK, auto_increment)
- `checklist_id` (int, NOT NULL, indexed, FK → checklists)
- `item_order` (int, NOT NULL)
- `description` (text, NOT NULL)
- `requires_photo` (tinyint 1, default 0)
- `photo_url` (varchar 500)
- `is_completed` (tinyint 1, default 0)
- `completed_at` (datetime)
- `notes` (text)

#### `quality_questions` - Preguntas de calidad
- `id` (PK, auto_increment)
- `role` (enum: planchero, cajero, NOT NULL)
- `question` (text, NOT NULL)
- `requires_photo` (tinyint 1, default 0)
- `order_index` (int, default 0)
- `active` (tinyint 1, default 1)
- `created_at` (timestamp)

#### `quality_checklists` - Checklists de calidad completados
- `id` (PK, auto_increment)
- `role` (enum: planchero, cajero, NOT NULL, indexed)
- `checklist_date` (date, NOT NULL)
- `responses` (JSON, NOT NULL)
- `total_questions`, `passed_questions` (int, NOT NULL)
- `score_percentage` (decimal 5,2, NOT NULL)
- `created_at`, `updated_at` (timestamp)

### Food Trucks y Ubicaciones

#### `food_trucks` - Camiones de comida
- `id` (PK, auto_increment)
- `nombre` (varchar 255, NOT NULL)
- `descripcion` (text)
- `direccion` (varchar 500, NOT NULL)
- `latitud` (decimal 10,8, NOT NULL)
- `longitud` (decimal 11,8, NOT NULL)
- `horario_inicio`, `horario_fin` (time, default 10:00/22:00)
- `activo` (tinyint 1, default 1)
- `tarifa_delivery` (int, default 2000)
- `usa_horarios_personalizados` (tinyint 1, default 0)
- `created_at`, `updated_at` (timestamp)

#### `food_truck_schedules` - Horarios personalizados
- `id` (PK, auto_increment)
- `food_truck_id` (int, NOT NULL, indexed, FK → food_trucks)
- `day_of_week` (tinyint, NOT NULL) - 0=Domingo, 6=Sábado
- `horario_inicio`, `horario_fin` (time, NOT NULL)
- `activo` (tinyint 1, default 1)
- `created_at`, `updated_at` (timestamp)

### Sistema

#### `system_config` - Configuración del sistema
- `id` (PK, auto_increment)
- `config_key` (varchar 100, UNIQUE, NOT NULL)
- `config_value` (text)
- `description` (text)
- `updated_at` (timestamp)

#### `php_sessions` - Sesiones PHP
- `session_id` (varchar 128, PK)
- `session_data` (text, NOT NULL)
- `last_activity` (timestamp, indexed)

## 🔑 Índices Importantes

### Performance Indexes
- `usuarios`: google_id, es_militar_rl6, rut, credito_bloqueado, fecha_ultimo_pago
- `products`: category_id, subcategory_id, stock_quantity, is_active
- `tuu_orders`: user_id, order_number (UNIQUE), status, payment_status, created_at
- `ingredients`: name, current_stock, expiry_date
- `inventory_transactions`: transaction_type, ingredient_id, product_id, order_reference, created_at
- `caja_movimientos`: tipo, order_reference, fecha_movimiento
- `site_visits`: ip_address, session_id, visit_date

### Foreign Keys
- Todas las relaciones entre tablas usan índices en claves foráneas
- Cascadas configuradas según necesidad de negocio

## 📝 Notas Importantes

### Formato Chileno
- Montos en pesos chilenos (sin decimales en display)
- Formato: `$15.990` (punto como separador de miles)
- Función: `toLocaleString('es-CL')`

### JSON Fields
- `combo_data`: Estructura de combos personalizados
- `delivery_extras_items`: Items extras de delivery
- `responses` (quality_checklists): Respuestas de checklist
- `notification_history`: Historial de notificaciones

### Timestamps
- Todas las tablas principales tienen `created_at` y `updated_at`
- Auto-update en `updated_at` con `ON UPDATE CURRENT_TIMESTAMP`

### Soft Deletes
- No se usa soft delete, se usa campo `is_active` o `activo`
- Permite reactivación de registros

### Enums
- Preferencia por enums para estados y tipos
- Facilita validación a nivel de BD
- Mejor performance que varchar con checks
