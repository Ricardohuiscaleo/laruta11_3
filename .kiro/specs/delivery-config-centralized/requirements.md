# Documento de Requisitos — Centralización de Configuración de Delivery

## Introducción

Actualmente el sistema de tarifas de delivery de La Ruta 11 tiene parámetros de pricing dispersos y hardcodeados en múltiples archivos del frontend y backend:

- El recargo por tarjeta ($500) está hardcodeado en `app3/src/components/CheckoutApp.jsx`, `caja3/src/components/CheckoutApp.jsx` y `caja3/src/components/MenuApp.jsx`, y se suma al campo `delivery_fee` en `tuu_orders` sin trazabilidad — no se puede distinguir cuánto es tarifa de delivery real vs recargo tarjeta en reportes.
- La fórmula de recargo por distancia (cada 2km más allá de 6km = +$1.000) está hardcodeada en `app3/api/location/get_delivery_fee.php` y `app3/api/create_order.php`.
- El factor de descuento RL6 (0.2857 en app3, 0.7143 en caja3) está hardcodeado en los componentes React de checkout.
- La tarifa base ($3.500) es el único parámetro que vive en BD (`food_trucks.tarifa_delivery`).
- No existe UI de administración para modificar estos parámetros — cualquier cambio requiere deploy de código.
- No existe columna `card_surcharge` en `tuu_orders` — el recargo se mezcla con `delivery_fee`.

Este feature centraliza todos los parámetros de pricing de delivery en una tabla de configuración en BD, expone una API CRUD en mi3-backend, crea una sección de administración en mi3-frontend, agrega trazabilidad del recargo tarjeta en `tuu_orders`, y migra app3/caja3 para leer la configuración desde BD en lugar de valores hardcodeados.

## Glosario

- **Delivery_Config**: Tabla nueva `delivery_config` en la base de datos MySQL principal (laruta11) que almacena todos los parámetros de pricing de delivery como pares clave-valor tipados.
- **Tarifa_Base**: Monto base cobrado por delivery independiente de la distancia. Actualmente $3.500, almacenado en `food_trucks.tarifa_delivery`.
- **Recargo_Tarjeta**: Monto adicional cobrado cuando el pedido es delivery y el método de pago es tarjeta. Actualmente $500, hardcodeado en frontend.
- **Umbral_Distancia_Km**: Distancia en kilómetros hasta la cual no se cobra recargo por distancia. Actualmente 6km, hardcodeado en PHP.
- **Recargo_Por_Tramo**: Monto cobrado por cada tramo adicional de distancia más allá del Umbral_Distancia_Km. Actualmente $1.000, hardcodeado en PHP.
- **Tamaño_Tramo_Km**: Tamaño en kilómetros de cada tramo para el cálculo de recargo por distancia. Actualmente 2km, hardcodeado en PHP.
- **Factor_Descuento_RL6**: Factor decimal aplicado al delivery fee bruto para calcular el descuento RL6. Actualmente 0.2857 (equivalente a 28.57% de descuento), hardcodeado en frontend.
- **API_Delivery_Config**: Endpoints REST en mi3-backend (Laravel 11) para leer y actualizar la configuración de delivery.
- **Seccion_Delivery_Config**: Nueva sección en mi3-frontend (AdminShell SPA) para administrar los parámetros de delivery.
- **Endpoint_Config_Publico**: Endpoint PHP en app3/caja3 que lee la configuración de delivery desde BD y la retorna como JSON para consumo del frontend.
- **AdminShell**: Componente SPA principal de mi3-frontend (`AdminShell.tsx`) que gestiona secciones lazy-loaded con routing por URL.

## Requisitos


### Requisito 1: Tabla de Configuración de Delivery en BD

**User Story:** Como administrador, quiero que todos los parámetros de pricing de delivery estén almacenados en una tabla de la base de datos, para poder modificarlos sin necesidad de cambios en el código.

#### Criterios de Aceptación

1. THE base de datos MySQL principal (laruta11) SHALL contener una tabla `delivery_config` con los campos: `id` (auto-increment PK), `config_key` (varchar 50, UNIQUE, NOT NULL), `config_value` (varchar 255, NOT NULL), `description` (varchar 255), `updated_by` (varchar 100), `updated_at` (timestamp auto-update), `created_at` (timestamp).
2. THE tabla `delivery_config` SHALL contener los siguientes registros iniciales con sus valores actuales de producción: `tarifa_base` = `3500`, `card_surcharge` = `500`, `distance_threshold_km` = `6`, `surcharge_per_bracket` = `1000`, `bracket_size_km` = `2`, `rl6_discount_factor` = `0.2857`.
3. THE tabla `delivery_config` SHALL tener un índice UNIQUE en `config_key` para garantizar que cada parámetro exista una sola vez.
4. WHEN se consulta un `config_key` que no existe en la tabla, THE sistema que consume la configuración SHALL usar un valor por defecto hardcodeado como fallback para evitar interrupciones de servicio.

### Requisito 2: Columna card_surcharge en tuu_orders para Trazabilidad

**User Story:** Como administrador, quiero que el recargo por tarjeta se almacene en una columna separada en la tabla de órdenes, para poder distinguir en reportes cuánto es tarifa de delivery real y cuánto es recargo por tarjeta.

#### Criterios de Aceptación

1. THE tabla `tuu_orders` SHALL contener una nueva columna `card_surcharge` de tipo `DECIMAL(10,2)` con valor por defecto `0.00`, ubicada después de la columna `delivery_fee`.
2. WHEN se crea una orden de tipo delivery con método de pago tarjeta, THE sistema de creación de órdenes SHALL almacenar el monto del recargo tarjeta en la columna `card_surcharge` de forma separada del campo `delivery_fee`.
3. THE columna `delivery_fee` en `tuu_orders` SHALL contener únicamente la tarifa de delivery (tarifa base + recargo distancia), sin incluir el recargo por tarjeta.
4. WHEN se consultan reportes de ventas, THE sistema SHALL poder calcular el total cobrado al cliente como `delivery_fee - delivery_discount + card_surcharge` usando columnas separadas.

### Requisito 3: API CRUD de Configuración de Delivery en mi3-backend

**User Story:** Como desarrollador, quiero endpoints REST en mi3-backend para leer y actualizar la configuración de delivery, para que la sección de administración pueda gestionar los parámetros.

#### Criterios de Aceptación

1. THE API_Delivery_Config SHALL exponer un endpoint `GET /api/delivery-config` que retorne todos los registros de la tabla `delivery_config` como un array JSON con los campos `config_key`, `config_value`, `description`, `updated_by`, `updated_at`.
2. THE API_Delivery_Config SHALL exponer un endpoint `PUT /api/delivery-config` que reciba un array de objetos `{config_key, config_value}` y actualice los valores correspondientes en la tabla `delivery_config`, registrando el usuario autenticado en `updated_by`.
3. WHEN el endpoint `PUT /api/delivery-config` recibe un `config_value` no numérico para una clave que requiere valor numérico (`tarifa_base`, `card_surcharge`, `distance_threshold_km`, `surcharge_per_bracket`, `bracket_size_km`), THE API_Delivery_Config SHALL retornar un error 422 con mensaje descriptivo.
4. WHEN el endpoint `PUT /api/delivery-config` recibe un `rl6_discount_factor` fuera del rango 0.0 a 1.0, THE API_Delivery_Config SHALL retornar un error 422 con mensaje descriptivo.
5. THE API_Delivery_Config SHALL requerir autenticación válida de mi3-backend para ambos endpoints.
6. WHEN el endpoint `PUT /api/delivery-config` actualiza valores exitosamente, THE API_Delivery_Config SHALL retornar los registros actualizados con sus nuevos valores y timestamps.

### Requisito 4: Sección de Administración de Delivery Config en mi3-frontend

**User Story:** Como administrador, quiero una sección en el panel de administración de mi3 donde pueda ver y editar los parámetros de pricing de delivery, para no depender de un desarrollador para cambiar precios.

#### Criterios de Aceptación

1. THE AdminShell SHALL incluir una nueva sección `delivery-config` accesible desde el sidebar, registrada como lazy-loaded section siguiendo el patrón existente de `SectionKey` en `AdminShell.tsx`.
2. THE Seccion_Delivery_Config SHALL mostrar todos los parámetros de delivery en un formulario editable con: nombre legible del parámetro, valor actual, campo de edición, y descripción de cada parámetro.
3. THE Seccion_Delivery_Config SHALL mostrar una vista previa en tiempo real del cálculo de delivery fee usando los valores editados, con un ejemplo: "Para 8km con tarjeta: tarifa_base + ceil((8 - threshold) / bracket) × surcharge + card_surcharge = $X".
4. WHEN el administrador modifica valores y presiona "Guardar", THE Seccion_Delivery_Config SHALL enviar los cambios al endpoint `PUT /api/delivery-config` y mostrar confirmación visual de éxito o error.
5. WHEN el administrador ingresa un valor inválido (texto en campo numérico, factor fuera de rango), THE Seccion_Delivery_Config SHALL mostrar validación inline antes de permitir el envío.
6. THE Seccion_Delivery_Config SHALL mostrar la fecha y usuario de la última modificación de cada parámetro.

### Requisito 5: Endpoint Público de Configuración para app3 y caja3

**User Story:** Como desarrollador, quiero un endpoint PHP en app3/caja3 que lea la configuración de delivery desde BD, para que los frontends puedan obtener los parámetros actualizados sin hardcodear valores.

#### Criterios de Aceptación

1. THE Endpoint_Config_Publico SHALL ser un archivo PHP ubicado en `app3/api/delivery/get_config.php` (y su equivalente en caja3) que retorne todos los parámetros de `delivery_config` como un objeto JSON con claves: `tarifa_base`, `card_surcharge`, `distance_threshold_km`, `surcharge_per_bracket`, `bracket_size_km`, `rl6_discount_factor`.
2. THE Endpoint_Config_Publico SHALL retornar valores numéricos parseados (integer para montos y distancias, float para el factor RL6), no strings.
3. IF la tabla `delivery_config` no existe o la consulta falla, THEN THE Endpoint_Config_Publico SHALL retornar los valores por defecto hardcodeados: `tarifa_base=3500`, `card_surcharge=500`, `distance_threshold_km=6`, `surcharge_per_bracket=1000`, `bracket_size_km=2`, `rl6_discount_factor=0.2857`.
4. THE Endpoint_Config_Publico SHALL incluir un campo `loaded_from` con valor `"database"` o `"defaults"` para indicar el origen de los datos.

### Requisito 6: Migración de app3 Frontend para Leer Config desde BD

**User Story:** Como desarrollador, quiero que app3 CheckoutApp lea los parámetros de delivery desde BD en lugar de valores hardcodeados, para que los cambios de configuración se reflejen sin deploy.

#### Criterios de Aceptación

1. WHEN el componente CheckoutApp de app3 se monta, THE CheckoutApp SHALL hacer un fetch al Endpoint_Config_Publico para obtener los parámetros de delivery y almacenarlos en estado local.
2. WHEN el CheckoutApp de app3 calcula el recargo por tarjeta, THE CheckoutApp SHALL usar el valor `card_surcharge` obtenido de la configuración en lugar del valor hardcodeado `500`.
3. WHEN el CheckoutApp de app3 calcula el descuento RL6, THE CheckoutApp SHALL usar el valor `rl6_discount_factor` obtenido de la configuración en lugar del valor hardcodeado `0.2857`.
4. IF el fetch al Endpoint_Config_Publico falla, THEN THE CheckoutApp de app3 SHALL usar los valores por defecto hardcodeados como fallback sin interrumpir el flujo de compra.
5. WHEN el CheckoutApp de app3 envía la orden, THE CheckoutApp SHALL enviar el campo `card_surcharge` como valor separado en el payload, en lugar de sumarlo a `delivery_fee`.

### Requisito 7: Migración de caja3 Frontend para Leer Config desde BD

**User Story:** Como desarrollador, quiero que caja3 CheckoutApp y MenuApp lean los parámetros de delivery desde BD en lugar de valores hardcodeados, para mantener consistencia con app3.

#### Criterios de Aceptación

1. WHEN el componente CheckoutApp de caja3 se monta, THE CheckoutApp SHALL hacer un fetch al Endpoint_Config_Publico para obtener los parámetros de delivery y almacenarlos en estado local.
2. WHEN el CheckoutApp de caja3 calcula el recargo por tarjeta, THE CheckoutApp SHALL usar el valor `card_surcharge` obtenido de la configuración en lugar del valor hardcodeado `500`.
3. WHEN el CheckoutApp de caja3 calcula el descuento RL6, THE CheckoutApp SHALL usar el valor `rl6_discount_factor` obtenido de la configuración en lugar del factor hardcodeado `0.7143` (que es `1 - 0.2857`).
4. WHEN el componente MenuApp de caja3 calcula el recargo por tarjeta, THE MenuApp SHALL usar el valor `card_surcharge` obtenido de la configuración en lugar del valor hardcodeado `500`.
5. WHEN el MenuApp de caja3 envía la orden, THE MenuApp SHALL enviar el campo `card_surcharge` como valor separado en el payload, en lugar de sumarlo a `delivery_fee`.
6. IF el fetch al Endpoint_Config_Publico falla, THEN THE CheckoutApp y MenuApp de caja3 SHALL usar los valores por defecto hardcodeados como fallback sin interrumpir el flujo de venta.

### Requisito 8: Migración del Cálculo Server-Side de Delivery Fee en PHP

**User Story:** Como desarrollador, quiero que los scripts PHP de cálculo de delivery fee lean los parámetros desde BD, para que la fórmula de recargo por distancia sea configurable.

#### Criterios de Aceptación

1. WHEN `get_delivery_fee.php` calcula el recargo por distancia, THE script SHALL leer `distance_threshold_km`, `surcharge_per_bracket`, y `bracket_size_km` desde la tabla `delivery_config` en lugar de usar los valores hardcodeados `6`, `1000`, y `2`.
2. WHEN `create_order.php` recalcula el delivery fee server-side, THE script SHALL leer los mismos parámetros desde `delivery_config` para el cálculo de recargo por distancia.
3. WHEN `create_order.php` recibe un campo `card_surcharge` en el payload, THE script SHALL almacenar ese valor en la nueva columna `card_surcharge` de `tuu_orders` y validar que coincida con el valor configurado en BD (o $0 si no aplica).
4. IF la tabla `delivery_config` no existe o la consulta falla, THEN THE scripts PHP SHALL usar los valores por defecto hardcodeados: `distance_threshold_km=6`, `surcharge_per_bracket=1000`, `bracket_size_km=2`.
5. THE script `create_order.php` SHALL dejar de sumar el recargo tarjeta al campo `delivery_fee` y en su lugar almacenarlo en `card_surcharge`.

### Requisito 9: Consistencia de Fórmula de Descuento RL6 entre app3 y caja3

**User Story:** Como desarrollador, quiero que ambas aplicaciones usen la misma fórmula de descuento RL6 leída desde BD, para eliminar la discrepancia actual entre `0.2857` (app3) y `0.7143` (caja3).

#### Criterios de Aceptación

1. THE tabla `delivery_config` SHALL almacenar `rl6_discount_factor` como el porcentaje de descuento expresado en decimal (0.2857 = 28.57% de descuento).
2. WHEN app3 calcula el descuento RL6, THE CheckoutApp SHALL calcular: `descuento = fee_bruto × rl6_discount_factor`.
3. WHEN caja3 calcula el descuento RL6, THE CheckoutApp y MenuApp SHALL calcular: `fee_con_descuento = fee_bruto × (1 - rl6_discount_factor)`, produciendo el mismo resultado neto que app3.
4. FOR ALL valores válidos de `rl6_discount_factor` entre 0.0 y 1.0, THE cálculo de descuento en app3 y caja3 SHALL producir el mismo monto final de delivery fee para un mismo fee bruto (propiedad round-trip).
