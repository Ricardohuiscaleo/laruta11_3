# Implementation Plan: Packaging Consumo Comandas

## Overview

Agregar registro manual de consumo de bolsas (packaging) en MiniComandas. Se implementa en 3 partes: (1) endpoint PHP para registrar transacciones de consumo, (2) estado y helpers en MiniComandas.jsx, (3) JSX inline de los steppers y wiring con los flujos de entrega/despacho. Se usa fast-check para property-based tests de la lógica pura.

## Tasks

- [x] 1. Crear endpoint PHP `register_packaging_consumption.php`
  - [x] 1.1 Crear `caja3/api/register_packaging_consumption.php` con la lógica completa
    - Cargar config y crear PDO siguiendo el patrón de `ajuste_inventario.php`
    - Leer JSON input (`order_number`, `bolsa_grande`, `bolsa_mediana`), validar campos requeridos
    - Definir constante `$PACKAGING_BAGS` con ids 130 y 167
    - Guard de idempotencia: `SELECT COUNT(*) FROM inventory_transactions WHERE order_reference = ? AND transaction_type = 'consumption' AND ingredient_id IN (130, 167)`
    - Para cada bolsa con cantidad > 0: leer `current_stock`, insertar en `inventory_transactions` con `transaction_type='consumption'`, cantidad negativa, `unit='unidad'`, `previous_stock`, `new_stock`, `order_reference`, y `notes` descriptivo
    - Actualizar `current_stock` en `ingredients`
    - Si stock < cantidad, agregar warning (no bloquear)
    - Retornar JSON con `success`, `transactions`, `warnings`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

  - [ ]* 1.2 Write property test: Transaction creation correctness (fast-check)
    - **Property 3: Transaction creation correctness**
    - Generar combinaciones aleatorias de cantidades (0-10) para bolsa_grande y bolsa_mediana, verificar que se crea exactamente una transacción por bolsa con cantidad > 0, con los campos correctos
    - **Validates: Requirements 4.1, 4.2, 4.4**

  - [ ]* 1.3 Write property test: Stock update consistency (fast-check)
    - **Property 4: Stock update consistency**
    - Generar stocks iniciales y cantidades aleatorias, verificar que `new_stock = previous_stock - cantidad` y que `current_stock` se actualiza correctamente
    - **Validates: Requirements 4.3**

  - [ ]* 1.4 Write property test: Idempotency guard (fast-check)
    - **Property 5: Idempotency guard**
    - Generar order_numbers aleatorios, llamar dos veces con mismos datos, verificar que la segunda llamada es rechazada
    - **Validates: Requirements 4.5**

  - [ ]* 1.5 Write property test: Negative stock with warning (fast-check)
    - **Property 6: Negative stock with warning**
    - Generar escenarios donde stock < cantidad, verificar que la transacción se crea y se incluye warning en la respuesta
    - **Validates: Requirements 4.6**

- [x] 2. Checkpoint - Verificar endpoint PHP
  - Ensure all tests pass, ask the user if questions arise.

- [x] 3. Agregar estado y helpers de packaging en MiniComandas.jsx
  - [x] 3.1 Agregar estado `packagingQty` y funciones helper
    - Agregar `const [packagingQty, setPackagingQty] = useState({});` junto a los otros useState (~línea 22)
    - Agregar función `getPackaging(orderId, deliveryType)` que retorna defaults según tipo de pedido (delivery → bolsa_grande: 1, pickup → ambas 0)
    - Agregar función `setPackagingValue(orderId, bagType, delta, deliveryType)` con clamp [0, 10]
    - _Requirements: 1.3, 1.4, 1.5, 1.6, 1.7_

  - [x] 3.2 Agregar función `registerPackaging`
    - Agregar junto a `deliverOrder` y `dispatchToDelivery` (~línea 370)
    - Hace POST a `/api/register_packaging_consumption.php` con `order_number`, `bolsa_grande`, `bolsa_mediana`
    - Si ambas cantidades son 0, retorna `true` sin llamar a la API
    - Si falla, muestra `alert()` de advertencia y retorna `false` (no bloquea)
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 5.1_

  - [ ]* 3.3 Write property test: Stepper initialization depends on order type (fast-check)
    - **Property 1: Stepper initialization depends on order type**
    - Generar orders aleatorios con delivery_type random (`pickup`/`delivery`), verificar que `getPackaging` retorna los valores iniciales correctos
    - **Validates: Requirements 1.3, 1.4**

  - [ ]* 3.4 Write property test: Stepper value bounds invariant (fast-check)
    - **Property 2: Stepper value bounds invariant**
    - Generar secuencias aleatorias de operaciones (+1, -1) sobre un stepper, verificar que el valor siempre está en [0, 10]
    - **Validates: Requirements 1.5, 1.6, 1.7**

- [x] 4. Insertar JSX del Packaging Stepper Area en renderOrderCard
  - [x] 4.1 Agregar el bloque JSX de steppers en `renderOrderCard`
    - Insertar justo antes del `<div className="flex flex-col gap-2">` de botones de acción y después del bloque de fotos delivery
    - Controlar visibilidad: ocultar para delivery en fase 2 (`order_status === 'ready'`), mostrar para pickup activo y delivery fase 1
    - Renderizar stepper para cada bolsa con imagen miniatura (24×24px) desde `/bolsa_deliverys/`, label abreviado ("Grande"/"Mediana"), botones "−"/"+" y valor numérico
    - Aplicar highlight visual (fondo `bg-amber-200`) cuando valor > 0
    - Respetar maxHeight 60px en el contenedor
    - _Requirements: 1.1, 1.2, 6.1, 6.2, 6.3, 7.1, 7.2, 7.3_

  - [ ]* 4.2 Write property test: Stepper visibility rules (fast-check)
    - **Property 7: Stepper visibility rules**
    - Generar combinaciones de `delivery_type` × `order_status`, verificar que la visibilidad es correcta: visible para pickup activo, visible para delivery fase 1, oculto para delivery fase 2
    - **Validates: Requirements 6.1, 6.2, 6.3**

- [x] 5. Integrar packaging con flujos de entrega y despacho
  - [x] 5.1 Modificar `deliverOrder` para llamar a `registerPackaging`
    - Agregar llamada a `registerPackaging` antes del fetch a `update_order_status.php`
    - Solo para pedidos que NO son delivery (pickup), ya que delivery registra packaging en despacho (fase 1)
    - _Requirements: 2.1, 2.2, 2.3, 5.1, 5.2_

  - [x] 5.2 Modificar `dispatchToDelivery` para llamar a `registerPackaging`
    - Agregar llamada a `registerPackaging` antes del fetch a `update_order_status.php`
    - Siempre para pedidos delivery en fase 1
    - _Requirements: 3.1, 3.2, 3.3, 5.1, 5.2_

- [x] 6. Final checkpoint - Verificar integración completa
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests use fast-check and validate universal correctness properties from the design
- El endpoint PHP sigue el patrón existente de `ajuste_inventario.php` (config loading, PDO, JSON response)
- Los steppers se renderizan inline en `renderOrderCard()` sin crear componente nuevo
- Ingredient IDs: Bolsa Grande = 130, Bolsa Mediana = 167
