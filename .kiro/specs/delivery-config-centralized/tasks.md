# Plan de Implementación: Centralización de Configuración de Delivery

## Resumen

Centralizar los 6 parámetros de pricing de delivery en una tabla `delivery_config` en MySQL, exponer API CRUD en mi3-backend (Laravel), crear sección admin en mi3-frontend (React/TS), crear endpoint PHP público para app3/caja3, y migrar todos los consumidores (frontends React + scripts PHP) para leer config desde BD en lugar de valores hardcodeados. Agregar columna `card_surcharge` en `tuu_orders` para trazabilidad.

## Tareas

- [ ] 1. Migraciones SQL y modelo Eloquent
  - [x] 1.1 Crear migración Laravel `create_delivery_config_table` con seed de los 6 parámetros iniciales
    - Crear archivo en `mi3/backend/database/migrations/` con CREATE TABLE + INSERT de valores de producción
    - Tabla: `id`, `config_key` (VARCHAR 50 UNIQUE), `config_value` (VARCHAR 255), `description`, `updated_by`, `updated_at`, `created_at`
    - Seed: tarifa_base=3500, card_surcharge=500, distance_threshold_km=6, surcharge_per_bracket=1000, bracket_size_km=2, rl6_discount_factor=0.2857
    - _Requisitos: 1.1, 1.2, 1.3_

  - [x] 1.2 Crear migración Laravel `add_card_surcharge_to_tuu_orders`
    - ALTER TABLE `tuu_orders` ADD COLUMN `card_surcharge` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `delivery_fee`
    - _Requisitos: 2.1_

  - [x] 1.3 Crear modelo Eloquent `DeliveryConfig`
    - Archivo: `mi3/backend/app/Models/DeliveryConfig.php`
    - Table: `delivery_config`, fillable: `config_key`, `config_value`, `description`, `updated_by`
    - Método estático `getAllAsMap()` que retorna array asociativo ['tarifa_base' => '3500', ...]
    - _Requisitos: 1.1, 1.4_

  - [~] 1.4 Ejecutar migraciones y verificar que la tabla y columna existen
    - Ejecutar `php artisan migrate` en mi3/backend
    - Verificar que `delivery_config` tiene 6 registros y `tuu_orders` tiene columna `card_surcharge`
    - _Requisitos: 1.1, 1.2, 2.1_

- [x] 2. API CRUD en mi3-backend (Laravel)
  - [x] 2.1 Crear `UpdateDeliveryConfigRequest` FormRequest
    - Archivo: `mi3/backend/app/Http/Requests/Admin/UpdateDeliveryConfigRequest.php`
    - Validar: `items` array requerido, `items.*.config_key` in:tarifa_base,card_surcharge,..., `items.*.config_value` requerido
    - Validación custom: valores numéricos para montos/distancias, rl6_discount_factor en [0.0, 1.0]
    - _Requisitos: 3.3, 3.4_

  - [x] 2.2 Crear `DeliveryConfigController` con endpoints GET y PUT
    - Archivo: `mi3/backend/app/Http/Controllers/Admin/DeliveryConfigController.php`
    - `index()`: GET retorna todos los registros de delivery_config como JSON
    - `update()`: PUT recibe array de {config_key, config_value}, valida con FormRequest, actualiza en BD, registra `updated_by` con usuario autenticado
    - _Requisitos: 3.1, 3.2, 3.5, 3.6_

  - [x] 2.3 Registrar rutas en `routes/api.php`
    - Agregar dentro del grupo admin autenticado: GET y PUT `delivery-config`
    - _Requisitos: 3.1, 3.5_

  - [ ]* 2.4 Escribir property test para validación de API (Property 4)
    - **Property 4: Validación de API rechaza valores inválidos**
    - Generar strings no numéricos para claves numéricas y floats fuera de [0,1] para rl6_discount_factor
    - Verificar que la función de validación los rechaza
    - **Valida: Requisitos 3.3, 3.4, 4.5**

  - [ ]* 2.5 Escribir property test para round-trip de configuración (Property 7)
    - **Property 7: Round-trip de configuración via API**
    - Generar valores válidos aleatorios para los 6 parámetros, simular PUT + GET, verificar que retorna los mismos valores
    - **Valida: Requisitos 3.2, 3.6**

- [ ] 3. Checkpoint — Verificar backend Laravel
  - Ejecutar migraciones y tests, asegurar que GET/PUT funcionan correctamente. Preguntar al usuario si hay dudas.

- [x] 4. Sección admin en mi3-frontend
  - [x] 4.1 Crear componente `DeliveryConfigSection.tsx`
    - Archivo: `mi3/frontend/components/admin/sections/DeliveryConfigSection.tsx`
    - Fetch GET `/api/v1/admin/delivery-config` al montar
    - Formulario editable con los 6 parámetros: nombre legible, valor actual, campo de edición, descripción
    - Validación inline: campos numéricos, rl6_discount_factor en [0,1], campos requeridos
    - Vista previa de cálculo en tiempo real: "Para 8km con tarjeta: tarifa_base + ceil((8 - threshold) / bracket) × surcharge + card_surcharge = $X"
    - Botón Guardar → PUT `/api/v1/admin/delivery-config`
    - Mostrar fecha y usuario de última modificación por parámetro
    - Manejo de errores: retry en GET, errores 422 inline, toast en 500
    - _Requisitos: 4.2, 4.3, 4.4, 4.5, 4.6_

  - [x] 4.2 Registrar sección en `AdminShell.tsx`
    - Agregar `'delivery-config'` al type `SectionKey`
    - Agregar en `SECTION_TITLES`: `'delivery-config': 'Config Delivery'`
    - Agregar lazy import en `sectionImports`
    - Agregar entrada en sidebar (AdminSidebarSPA / MobileBottomNavSPA si aplica)
    - _Requisitos: 4.1_

- [ ] 5. Checkpoint — Verificar panel admin completo
  - Asegurar que la sección se carga en AdminShell, el formulario muestra los 6 parámetros, la vista previa calcula correctamente, y guardar persiste en BD. Preguntar al usuario si hay dudas.

- [x] 6. Endpoint PHP público y helper de configuración
  - [x] 6.1 Crear `delivery_config_helper.php` en app3
    - Archivo: `app3/api/delivery/delivery_config_helper.php`
    - Función `get_delivery_config(PDO $pdo): array` que lee delivery_config de BD
    - Retorna array con valores tipados (int para montos, float para factor)
    - Fallback a defaults hardcodeados si la tabla no existe o la consulta falla
    - _Requisitos: 1.4, 5.3_

  - [x] 6.2 Crear `get_config.php` en app3
    - Archivo: `app3/api/delivery/get_config.php`
    - Usa `get_delivery_config()` del helper
    - Retorna JSON con las 6 claves + campo `loaded_from` ("database" o "defaults")
    - Valores numéricos parseados (int para montos/distancias, float para factor)
    - _Requisitos: 5.1, 5.2, 5.4_

  - [x] 6.3 Copiar `delivery_config_helper.php` y `get_config.php` a caja3
    - Archivos: `caja3/api/delivery/delivery_config_helper.php` y `caja3/api/delivery/get_config.php`
    - Misma lógica que app3 (ambas apps comparten BD laruta11)
    - _Requisitos: 5.1_

  - [ ]* 6.4 Escribir property test para fallback a defaults (Property 1)
    - **Property 1: Fallback a valores por defecto para config faltante**
    - Generar objetos de config parciales (claves faltantes aleatorias), pasarlos a función de merge con defaults
    - Verificar que el resultado siempre tiene las 6 claves con valores numéricos válidos
    - **Valida: Requisitos 1.4, 5.3, 6.4, 7.6, 8.4**

  - [ ]* 6.5 Escribir property test para tipado numérico (Property 5)
    - **Property 5: Tipado numérico en respuesta del endpoint público**
    - Generar strings numéricos aleatorios, pasarlos por función de parsing
    - Verificar que retorna int para montos y float para factor
    - **Valida: Requisitos 5.2, 5.4**

- [x] 7. Migración de scripts PHP server-side
  - [x] 7.1 Migrar `get_delivery_fee.php` en app3 para leer params de BD
    - Archivo: `app3/api/location/get_delivery_fee.php`
    - Importar helper, leer `distance_threshold_km`, `surcharge_per_bracket`, `bracket_size_km` desde BD
    - Reemplazar valores hardcodeados 6, 1000, 2 con valores de config
    - Fallback a defaults si falla lectura
    - _Requisitos: 8.1, 8.4_

  - [x] 7.2 Migrar `create_order.php` en app3 para leer params de BD y almacenar card_surcharge separado
    - Archivo: `app3/api/create_order.php`
    - Importar helper, leer params de delivery_config
    - Almacenar `card_surcharge` en columna separada de `tuu_orders` (no sumar a delivery_fee)
    - Validar card_surcharge del payload contra valor de BD
    - _Requisitos: 2.2, 2.3, 8.2, 8.3, 8.5_

  - [x] 7.3 Migrar `create_order.php` en caja3 con los mismos cambios
    - Archivo: `caja3/api/create_order.php`
    - Misma lógica: leer params de BD, almacenar card_surcharge separado
    - _Requisitos: 2.2, 2.3, 8.2, 8.3, 8.5_

  - [ ]* 7.4 Escribir property test para cálculo de delivery fee (Property 3)
    - **Property 3: Cálculo de delivery fee con parámetros dinámicos**
    - Generar combinaciones aleatorias de tarifa_base, distance_threshold_km, surcharge_per_bracket, bracket_size_km, distancia
    - Verificar: `tarifa_base + ceil(max(0, dist - threshold) / bracket) × surcharge`
    - Para distancias ≤ threshold, surcharge debe ser 0
    - **Valida: Requisitos 4.3, 8.1, 8.2**

- [ ] 8. Checkpoint — Verificar backend PHP
  - Asegurar que get_config.php retorna JSON correcto, get_delivery_fee.php calcula con params de BD, y create_order.php almacena card_surcharge separado. Preguntar al usuario si hay dudas.

- [x] 9. Migración de frontends React (app3 y caja3)
  - [x] 9.1 Migrar `CheckoutApp.jsx` de app3
    - Archivo: `app3/src/components/CheckoutApp.jsx`
    - Fetch a `/api/delivery/get_config.php` al montar, almacenar en estado
    - Reemplazar `500` hardcodeado con `config.card_surcharge`
    - Reemplazar `0.2857` hardcodeado con `config.rl6_discount_factor`
    - Enviar `card_surcharge` como campo separado en payload de orden (no sumar a delivery_fee)
    - Fallback a defaults si fetch falla
    - _Requisitos: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [x] 9.2 Migrar `CheckoutApp.jsx` de caja3
    - Archivo: `caja3/src/components/CheckoutApp.jsx`
    - Fetch a `/api/delivery/get_config.php` al montar, almacenar en estado
    - Reemplazar `500` hardcodeado con `config.card_surcharge`
    - Reemplazar `0.7143` hardcodeado con `(1 - config.rl6_discount_factor)`
    - Enviar `card_surcharge` como campo separado en payload
    - Fallback a defaults si fetch falla
    - _Requisitos: 7.1, 7.2, 7.3, 7.6_

  - [x] 9.3 Migrar `MenuApp.jsx` de caja3
    - Archivo: `caja3/src/components/MenuApp.jsx`
    - Fetch a `/api/delivery/get_config.php` al montar, almacenar en estado
    - Reemplazar `CARD_DELIVERY_SURCHARGE = 500` con `config.card_surcharge`
    - Reemplazar factor `0.7143` con `(1 - config.rl6_discount_factor)`
    - Enviar `card_surcharge` como campo separado en payload
    - Fallback a defaults si fetch falla
    - _Requisitos: 7.4, 7.5, 7.6_

  - [ ]* 9.4 Escribir property test para separación card_surcharge/delivery_fee (Property 2)
    - **Property 2: Separación de card_surcharge y delivery_fee en órdenes**
    - Generar valores válidos de tarifa_base, surcharge distancia, card_surcharge
    - Verificar que delivery_fee = tarifa_base + surcharge_distancia (sin card_surcharge)
    - Verificar que delivery_fee + card_surcharge = total anterior
    - **Valida: Requisitos 2.2, 2.3, 6.5, 7.5, 8.5**

  - [ ]* 9.5 Escribir property test para consistencia RL6 (Property 6)
    - **Property 6: Consistencia de descuento RL6 entre app3 y caja3**
    - Generar pares aleatorios de fee_bruto (0-100000) y rl6_discount_factor (0-1)
    - Verificar que `Math.round(fee - fee * factor)` === `Math.round(fee * (1 - factor))`
    - **Valida: Requisitos 9.2, 9.3, 9.4**

- [ ] 10. Checkpoint final — Verificar integración completa
  - Asegurar que todos los tests pasan, que la sección admin funciona end-to-end, que app3/caja3 leen config de BD, y que create_order almacena card_surcharge separado. Preguntar al usuario si hay dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- Los property tests usan `fast-check` (ya instalado en mi3-frontend) y validan propiedades de correctitud del diseño
- Los archivos PHP de app3 y caja3 comparten la misma BD `laruta11`, por lo que el helper es idéntico en ambas apps
