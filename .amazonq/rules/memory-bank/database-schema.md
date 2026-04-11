# La Ruta 11 - Database Schema

## 📊 Resumen
Base de datos MySQL compartida entre app3 (clientes) y caja3 (cajeros) con 65 tablas.
Última verificación contra producción: 2026-04-10.

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
- `instagram` (varchar 100)
- `lugar_nacimiento` (varchar 255)
- `nacionalidad` (varchar 20)
- `direccion_actual` (text)
- `ubicacion_actualizada` (timestamp)
- `total_sessions` (int, default 0)
- `total_time_seconds` (int, default 0)
- `last_session_duration` (int, default 0)
- `kanban_status` (enum: nuevo, revisando, entrevista, contratado, rechazado, default nuevo)
- `last_notification_sent` (timestamp)
- `notification_count` (int, default 0)
- `pending_notification` (tinyint 1, default 0)
- `notification_history` (longtext)

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
- `credito_disponible` (decimal 10,2, default 0.00)
- `updated_at` (timestamp, auto-update)
- `fecha_aprobacion_credito` (timestamp)

**R11 Credit Fields:**
- `es_credito_r11` (tinyint 1, default 0, indexed)
- `credito_r11_aprobado` (tinyint 1, default 0)
- `limite_credito_r11` (decimal 10,2, default 0.00)
- `credito_r11_usado` (decimal 10,2, default 0.00)
- `credito_r11_bloqueado` (tinyint 1, default 0, indexed)
- `fecha_aprobacion_r11` (timestamp)
- `fecha_ultimo_pago_r11` (date)
- `relacion_r11` (varchar 100)
- `carnet_qr_data` (JSON)

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
- `is_featured` (tinyint 1, default 0)
- `sale_price` (decimal 10,2)
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
- `delivery_type` (enum: pickup, delivery, cuartel, tv, default pickup)
- `delivery_address` (text)
- `delivery_fee` (decimal 10,2, default 0.00, indexed)
- `delivery_distance_km` (decimal 5,1)
- `delivery_duration_min` (int)
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
- `dispatch_photo_url` (varchar 500)
- `tv_order_id` (int)
- `pagado_con_credito_r11` (tinyint 1, default 0, indexed)
- `monto_credito_r11` (decimal 10,2, default 0.00)

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
- `tarifa_delivery` (int, default 2000) — **Producción: $3.500**
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

## 🆕 Tablas No Documentadas Previamente (verificadas 2026-04-10)

### RRHH y Nómina

#### `personal` - Personal/trabajadores
- `id` (PK, auto_increment)
- `nombre` (varchar 100, NOT NULL)
- `email` (varchar 150)
- `rol` (SET: administrador, cajero, planchero, delivery, seguridad, dueño, rider, default cajero)
- `activo` (tinyint 1, default 1)
- `created_at` (timestamp)
- `sueldo_base_seguridad`, `sueldo_base_cajero`, `sueldo_base_planchero`, `sueldo_base_admin` (decimal 10,2, default 0.00)
- `user_id` (int) — FK → usuarios
- `rut` (varchar 12)
- `telefono` (varchar 20)

#### `turnos` - Turnos de trabajo
- `id` (PK, auto_increment)
- `personal_id` (int, NOT NULL, indexed, FK → personal)
- `fecha` (date, NOT NULL)
- `tipo` (varchar 30, NOT NULL, default 'normal')
- `reemplazado_por` (int)
- `notas` (varchar 255)
- `created_at` (timestamp)
- `pagado_por` (enum: empresa, personal)
- `monto_reemplazo` (decimal 10,2, default 0.00)
- `pago_por` (varchar 30, default 'empresa')

#### `pagos_nomina` - Pagos de nómina
- `id` (PK, auto_increment)
- `mes` (date, NOT NULL, indexed)
- `personal_id` (int)
- `nombre` (varchar 100, NOT NULL)
- `monto` (decimal 10,2, NOT NULL)
- `es_externo` (tinyint 1, default 0)
- `notas` (varchar 255)
- `created_at` (timestamp)
- `centro_costo` (varchar 50, default 'ruta11')

#### `presupuesto_nomina` - Presupuesto mensual
- `mes` (date, PK, NOT NULL)
- `monto` (decimal 12,2, NOT NULL)
- `centro_costo` (varchar 50, PK, default 'ruta11')

#### `ajustes_sueldo` - Ajustes de sueldo
- `id` (PK, auto_increment)
- `personal_id` (int, NOT NULL, indexed)
- `categoria_id` (int)
- `mes` (date, NOT NULL)
- `monto` (decimal 10,2, NOT NULL)
- `concepto` (varchar 255, NOT NULL)
- `created_at` (timestamp)
- `notas` (varchar 255)

#### `ajustes_categorias` - Categorías de ajustes
- `id` (PK, auto_increment)
- `slug` (varchar 50, UNIQUE, NOT NULL)
- `nombre` (varchar 100, NOT NULL)
- `icono` (varchar 20, NOT NULL)
- `color` (varchar 20, NOT NULL)
- `signo_defecto` (char 1, NOT NULL, default '-')
- `orden` (int, default 0)

### TV Orders (Pedidos desde TV)

#### `tv_orders` - Órdenes desde pantalla TV
- `id` (PK, auto_increment)
- `total` (decimal 10,2, NOT NULL)
- `status` (enum: pendiente, en_proceso, enviado_cocina, pagado, cancelado, default pendiente)
- `created_at` (datetime)

#### `tv_order_items` - Items de órdenes TV
- `id` (PK, auto_increment)
- `order_id` (int, NOT NULL, indexed, FK → tv_orders)
- `product_id` (int, NOT NULL)
- `product_name` (varchar 255, NOT NULL)
- `price` (decimal 10,2, NOT NULL)
- `customizations` (JSON)

### POS Transactions

#### `tuu_pos_transactions` - Transacciones POS TUU
- `id` (PK, auto_increment)
- `sale_id` (varchar 50, UNIQUE, NOT NULL)
- `amount` (decimal 10,2, NOT NULL)
- `status` (varchar 50, NOT NULL, indexed)
- `pos_serial_number` (varchar 100, NOT NULL, indexed)
- `transaction_type` (varchar 50, NOT NULL)
- `payment_date_time` (datetime, NOT NULL, indexed)
- `items_json` (text)
- `extra_data_json` (text)
- `created_at`, `updated_at` (timestamp)

### Combos Extendido

#### `combo_selections` - Selecciones de combos
- `id` (PK, auto_increment)
- `combo_id` (int, NOT NULL, indexed, FK → combos)
- `selection_group` (varchar 50, NOT NULL)
- `product_id` (int, NOT NULL, indexed, FK → products)
- `additional_price` (decimal 10,2, default 0.00)
- `max_selections` (int, default 1)

### Usuarios Alternativo

#### `app_users` - Usuarios de app (legacy/alternativo)
- `id` (PK, auto_increment)
- `email` (varchar 255, UNIQUE, NOT NULL)
- `phone` (varchar 20)
- `name`, `last_name` (varchar 255)
- `birth_date` (date)
- `gender` (enum: M, F, O)
- `address` (text)
- `city` (varchar 100, indexed), `region` (varchar 100), `postal_code` (varchar 10)
- `registration_date` (timestamp, indexed)
- `last_login` (timestamp)
- `is_active` (tinyint 1, default 1)
- `total_orders` (int, default 0), `total_spent` (decimal 10,2, default 0.00)
- `favorite_category_id` (int)
- `preferred_payment_method` (varchar 50)
- `marketing_consent` (tinyint 1, default 0)
- `created_at`, `updated_at` (timestamp)

### Concurso/Juego

#### `concurso_registros` - Registros de concurso
- `id` (PK, auto_increment)
- `order_number` (varchar 50, UNIQUE)
- `customer_name` (varchar 255), `nombre` (varchar 100, NOT NULL)
- `rut` (varchar 12, UNIQUE), `email` (varchar 100, UNIQUE)
- `customer_phone`, `telefono` (varchar 20)
- `peso` (int), `fecha_nacimiento` (date, NOT NULL)
- `acepta_terminos` (tinyint 1, NOT NULL, default 1)
- `mayor_18` (tinyint 1, default 0)
- `image_url` (text)
- Campos TUU: `tuu_payment_request_id`, `tuu_idempotency_key`, `tuu_device_used`, `tuu_transaction_id`, `tuu_amount` (default 5000), etc.
- `payment_status`, `estado_pago` (enums de estado)
- `created_at`, `updated_at`, `fecha_registro`, `fecha_pago` (timestamps)

#### `concurso_state` - Estado del torneo
- `id` (PK, default 1)
- `tournament_data` (longtext, NOT NULL)
- `updated_at` (timestamp)

#### `concurso_tracking` - Tracking de visitas concurso
- `id` (PK, auto_increment)
- `source` (varchar 50, indexed, default 'DIRECT')
- `ip_address` (varchar 45)
- `user_agent` (text)
- `visit_date` (date, NOT NULL, indexed)
- `visit_time` (timestamp)
- `is_participant` (tinyint 1, indexed, default 0)
- `has_paid` (tinyint 1, default 0)

#### `participant_likes` - Likes de participantes
- `id` (PK, auto_increment)
- `participant_id` (varchar 50, NOT NULL, indexed)
- `viewer_id` (varchar 50, NOT NULL)
- `ip_address` (varchar 45)
- `created_at` (timestamp)

### Chat y Live

#### `chat_messages` - Mensajes de chat
- `id` (PK, auto_increment)
- `username` (varchar 50, NOT NULL)
- `message` (text, NOT NULL)
- `timestamp` (timestamp, indexed)
- `approved` (tinyint 1, default 1)
- `session_id` (varchar 255)
- `sender` (enum: user, admin, default user)
- `type` (varchar 255, default 'text')
- `file_path` (varchar 255)
- `telegram_message_id` (varchar 255)
- `created_at`, `updated_at` (timestamp)

#### `live_viewers` - Viewers de live stream
- `id` (varchar 50, PK)
- `ip_address` (varchar 45)
- `user_agent` (text)
- `last_seen` (timestamp, auto-update)
- `created_at` (timestamp)

#### `youtube_live` - Configuración YouTube Live
- `id` (PK, default 1)
- `original_url` (text)
- `embed_url` (varchar 255)
- `active` (tinyint 1, default 1)
- `updated_at` (timestamp)

### Otros

#### `checklist_templates` - Templates de checklist
- `id` (PK, auto_increment)
- `type` (enum: apertura, cierre, indexed)
- `item_order` (int, NOT NULL)
- `description` (text, NOT NULL)
- `requires_photo` (tinyint 1, default 0)
- `active` (tinyint 1, default 1)
- `created_at` (timestamp)

#### `product_edit_requests` - Solicitudes de edición de productos
- `id` (PK, auto_increment)
- `product_id` (int, NOT NULL)
- `old_name`, `new_name` (varchar 255)
- `old_description`, `new_description` (text)
- `cashier` (varchar 100)
- `status` (enum: pending, approved, rejected, default pending)
- `telegram_message_id` (int)
- `created_at` (timestamp)

#### `attempts` - Intentos de juego
- `id` (PK, auto_increment)
- `player_name` (varchar 50, NOT NULL, indexed)
- `time_achieved` (decimal 5,2, NOT NULL)
- `attempt_date` (timestamp, indexed)
- `ip_address` (varchar 45)

#### `user_locations` - Ubicaciones de usuarios
- `id` (PK, auto_increment)
- `user_id` (int, NOT NULL, indexed, FK → usuarios)
- `latitud` (decimal 10,8, NOT NULL), `longitud` (decimal 11,8, NOT NULL)
- `direccion` (text)
- `precision_metros` (int, default 0)
- `created_at` (timestamp, indexed)

#### `user_journey` - Journey de usuario en sitio
- `id` (PK, auto_increment)
- `session_id` (varchar 100, indexed)
- `page_sequence` (int)
- `page_url` (varchar 500)
- `time_spent` (int)
- `scroll_depth` (int)
- `exit_page` (tinyint 1, default 0)
- `timestamp` (timestamp)

#### `menu_categories`, `menu_subcategories` - Categorías/subcategorías de menú (legacy)

#### `inventory_transactions_backup_20251110` - Backup de transacciones de inventario
