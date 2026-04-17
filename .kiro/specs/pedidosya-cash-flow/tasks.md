# Implementation Plan: PedidosYA Cash Flow

## Overview

Implementar el flujo de pago "PedidosYA con Efectivo" en caja3, agregando el valor `pedidosya_cash` al ENUM de payment_method, un modal de selección Online/Efectivo en CheckoutApp, un cash modal en MiniComandas para confirmar pagos en efectivo, registro automático en caja_movimientos, y visualización en reportes (ArqueoApp, VentasDetalle).

## Tasks

- [x] 1. Agregar `pedidosya_cash` al ENUM de payment_method en base de datos
  - [x] 1.1 Crear script SQL para ALTER TABLE tuu_orders
    - Modificar la columna `payment_method` para incluir `pedidosya_cash` en el ENUM
    - Mantener todos los valores existentes: webpay, transfer, card, cash, pedidosya, rl6_credit, r11_credit
    - Guardar en `caja3/sql/add_pedidosya_cash_enum.sql`
    - _Requirements: 2.1_

- [x] 2. Implementar modal de selección PedidosYA en CheckoutApp
  - [x] 2.1 Agregar estado y modal de selección Online/Efectivo en CheckoutApp.jsx
    - Agregar estado `showPedidosYAModal`
    - Modificar el botón PedidosYA para abrir el modal en vez de crear orden directamente
    - Implementar modal inline con opciones "Online" y "Efectivo" y botón "Cancelar"
    - Validar formulario antes de abrir el modal (nombre, teléfono, dirección si delivery)
    - _Requirements: 1.1, 1.4, 1.5_
  - [x] 2.2 Implementar función `handlePedidosYACashPayment` en CheckoutApp.jsx
    - Crear función que envía la orden con `payment_method: 'pedidosya_cash'`
    - Reutilizar lógica de `handlePedidosYAPayment` cambiando solo el payment_method
    - "Online" llama a `handlePedidosYAPayment()` existente (payment_method: pedidosya)
    - "Efectivo" llama a `handlePedidosYACashPayment()` (payment_method: pedidosya_cash)
    - Redirigir a página de pedido pendiente tras crear orden
    - _Requirements: 1.2, 1.3, 2.2, 2.3_
  - [ ]* 2.3 Escribir property test: Validation blocks modal opening (Property 1)
    - **Property 1: Validation blocks modal opening**
    - Generar estados de formulario inválidos aleatorios con fast-check, verificar que `validateForm()` retorna false y el modal no se abre
    - **Validates: Requirements 1.5**

- [x] 3. Checkpoint - Verificar creación de órdenes pedidosya_cash
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Implementar Cash Modal y confirmación de pago en MiniComandas
  - [x] 4.1 Agregar etiqueta y ícono para `pedidosya_cash` en MiniComandas.jsx
    - Agregar case `pedidosya_cash` en `getPaymentIcon` → ícono `Banknote`
    - Agregar case `pedidosya_cash` en `getPaymentText` → `'PedidosYA Efectivo'`
    - Mostrar botón "CONFIRMAR PAGO" para pedidos con payment_method `pedidosya_cash` y payment_status `unpaid`
    - _Requirements: 3.1, 3.2, 3.3_
  - [x] 4.2 Implementar Cash Modal inline en MiniComandas.jsx
    - Agregar estados: `cashModalOrder`, `cashAmount`, `cashStep`
    - Modificar `confirmPayment` para abrir Cash Modal cuando `paymentMethod === 'pedidosya_cash'`
    - Implementar modal con campo de monto, botón "Monto Exacto", botones rápidos ($5.000, $10.000, $20.000)
    - Implementar cálculo de vuelto: si monto > total mostrar vuelto, si monto === total confirmar directo, si monto < total mostrar error
    - Implementar validación de monto vacío/cero
    - Reutilizar `formatCurrency` y lógica de `handleCashInput` del CheckoutApp
    - Soportar tecla Enter para avanzar al paso de confirmación
    - Incluir botón "Cancelar" que cierra el modal sin confirmar
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 7.1, 7.2, 7.3_
  - [ ]* 4.3 Escribir property test: Payment method display mapping completeness (Property 3)
    - **Property 3: Payment method display mapping completeness**
    - Iterar sobre todos los payment methods válidos, verificar que `getPaymentText` retorna label no vacío y `getPaymentIcon` retorna componente válido
    - **Validates: Requirements 3.1**
  - [ ]* 4.4 Escribir property test: Cash modal change calculation (Property 4)
    - **Property 4: Cash modal change calculation**
    - Generar pares aleatorios (total, amountPaid) con fast-check, verificar cálculo correcto de vuelto/faltante/pago exacto
    - **Validates: Requirements 4.3, 4.4, 4.5**
  - [ ]* 4.5 Escribir property test: Chilean currency formatting (Property 6)
    - **Property 6: Chilean currency formatting**
    - Generar enteros positivos aleatorios, verificar que `formatCurrency` produce formato chileno con separador de miles con punto
    - **Validates: Requirements 7.1, 7.2**

- [x] 5. Checkpoint - Verificar Cash Modal y visualización en MiniComandas
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Extender confirm_transfer_payment.php para pedidosya_cash
  - [x] 6.1 Modificar confirm_transfer_payment.php para registrar ingreso en caja para pedidosya_cash
    - Extender condición de registro en caja: `if (in_array($order['payment_method'], ['cash', 'pedidosya_cash']))`
    - Para `pedidosya_cash`, usar motivo "Venta PedidosYA Efectivo - Pedido #[order_number]"
    - Agregar mapping de `$payment_type` para `pedidosya_cash` → `'PedidosYA Efectivo'`
    - Calcular `saldo_nuevo = saldo_anterior + monto` al insertar en caja_movimientos
    - Mantener transacción con rollback en caso de error
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  - [ ]* 6.2 Escribir property test: Caja movimientos entry correctness (Property 5)
    - **Property 5: Caja movimientos entry correctness**
    - Generar montos y order_numbers aleatorios, verificar formato de motivo y cálculo de saldo
    - **Validates: Requirements 5.2, 5.4**
  - [x] 6.3 Conectar Cash Modal de MiniComandas con confirm_transfer_payment.php
    - Al confirmar pago en Cash Modal, llamar a `confirm_transfer_payment.php` con el order_id
    - Recargar lista de pedidos tras confirmación exitosa
    - Manejar errores de red/BD mostrando mensaje al cajero
    - _Requirements: 5.1, 7.4_

- [x] 7. Checkpoint - Verificar flujo completo de pago pedidosya_cash
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implementar reportes y visualización
  - [x] 8.1 Agregar categoría pedidosya_cash en get_sales_summary.php
    - Agregar `'pedidosya_cash' => ['count' => 0, 'total' => 0]` al array `$result`
    - Asegurar que la agrupación por payment_method incluye pedidosya_cash
    - _Requirements: 6.1_
  - [x] 8.2 Agregar tarjeta PedidosYA Efectivo en ArqueoApp.jsx
    - Agregar tarjeta separada "PedidosYA Efectivo" con ícono `Banknote`
    - Mostrar total y cantidad de pedidos pedidosya_cash
    - Desglosar PedidosYA Online vs PedidosYA Efectivo
    - _Requirements: 6.1_
  - [x] 8.3 Agregar badge y filtro pedidosya_cash en VentasDetalle.jsx
    - Agregar entrada en `getMethodBadge` para `pedidosya_cash` con label "PYA Efectivo" y color `bg-yellow-100 text-yellow-800`
    - Agregar `{ key: 'pedidosya_cash', icon: Banknote, label: 'PYA Efvo' }` al array de filtros
    - _Requirements: 6.2, 6.3_

- [x] 9. Final checkpoint - Verificar implementación completa
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada task referencia los requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental del flujo
- Property tests usan fast-check para validar propiedades de correctitud
- No se crean componentes nuevos: todo se implementa como modales inline y extensiones de componentes existentes
- `create_order.php` no requiere cambios funcionales ya que acepta cualquier string como payment_method
