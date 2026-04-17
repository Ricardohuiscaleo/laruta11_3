# Requirements Document

## Introduction

Este documento define los requisitos para implementar un nuevo flujo de pago "PedidosYA con Efectivo" en caja3 (caja.laruta11.cl). Actualmente, cuando un pedido de PedidosYA se paga en efectivo, el cajero debe registrar manualmente un ingreso a caja por el excedente. El nuevo flujo automatiza este proceso: al seleccionar PedidosYA como método de pago, se presenta una opción entre "Online" (flujo actual) y "Efectivo" (nuevo flujo). Si se elige "Efectivo", la orden se crea con método de pago `pedidosya_cash`, y al confirmar el pago en MiniComandas se abre un modal de pago en efectivo con cálculo de vuelto y registro automático en caja.

## Glossary

- **Checkout_App**: Componente React (`CheckoutApp.jsx`) en caja3 que gestiona la creación de pedidos y selección de método de pago.
- **MiniComandas**: Componente React (`MiniComandas.jsx`) en caja3 que muestra los pedidos activos y permite confirmar pagos, entregar y anular pedidos.
- **Cash_Modal**: Modal de pago en efectivo que muestra el total a pagar, permite ingresar el monto recibido del cliente, ofrece botones de monto rápido y calcula el vuelto.
- **PedidosYA_Method_Modal**: Modal que se muestra al seleccionar PedidosYA como método de pago en Checkout_App, ofreciendo las opciones "Online" y "Efectivo".
- **Caja_Movimientos**: Tabla MySQL `caja_movimientos` que registra todos los ingresos y retiros de la caja con saldo anterior y nuevo.
- **Tuu_Orders**: Tabla MySQL `tuu_orders` que almacena todas las órdenes del sistema con su método de pago, estado y montos.
- **Order_API**: Endpoint PHP `create_order.php` que crea órdenes en la base de datos.
- **Confirm_API**: Endpoint PHP `confirm_transfer_payment.php` que confirma pagos y registra ingresos en caja.
- **Arqueo_App**: Componente React (`ArqueoApp.jsx`) que muestra el resumen de ventas por método de pago.
- **Ventas_Detalle**: Componente React (`VentasDetalle.jsx`) que muestra el detalle de ventas con filtros por método de pago.
- **Payment_Method_Enum**: Campo `payment_method` en Tuu_Orders, actualmente con valores: webpay, transfer, card, cash, pedidosya, rl6_credit.

## Requirements

### Requirement 1: Modal de selección de sub-método PedidosYA

**User Story:** Como cajero, quiero que al seleccionar PedidosYA como método de pago se me pregunte si el pago es Online o Efectivo, para poder registrar correctamente el tipo de pago del pedido.

#### Acceptance Criteria

1. WHEN el cajero presiona el botón "PedidosYA" en Checkout_App, THE PedidosYA_Method_Modal SHALL mostrarse con dos opciones: "Online" y "Efectivo".
2. WHEN el cajero selecciona "Online" en PedidosYA_Method_Modal, THE Checkout_App SHALL crear la orden con payment_method `pedidosya` y redirigir a la página de pedido pendiente (flujo actual sin cambios).
3. WHEN el cajero selecciona "Efectivo" en PedidosYA_Method_Modal, THE Checkout_App SHALL crear la orden con payment_method `pedidosya_cash` y redirigir a la página de pedido pendiente.
4. THE PedidosYA_Method_Modal SHALL incluir un botón "Cancelar" que cierre el modal sin crear la orden.
5. WHILE el formulario de datos del cliente tiene errores de validación, THE PedidosYA_Method_Modal SHALL permanecer cerrado y THE Checkout_App SHALL mostrar los errores de validación.

### Requirement 2: Nuevo valor de payment_method en base de datos

**User Story:** Como sistema, quiero almacenar el método de pago `pedidosya_cash` en la base de datos, para diferenciar los pedidos PedidosYA pagados en efectivo de los pagados online.

#### Acceptance Criteria

1. THE Payment_Method_Enum SHALL incluir el valor `pedidosya_cash` como opción válida en la columna `payment_method` de Tuu_Orders.
2. WHEN Order_API recibe una orden con payment_method `pedidosya_cash`, THE Order_API SHALL almacenar la orden con payment_status `unpaid` y order_status `sent_to_kitchen`.
3. THE Order_API SHALL aceptar `pedidosya_cash` como valor válido de payment_method sin modificar el flujo de creación de orden existente.

### Requirement 3: Visualización de pedidos PedidosYA_Cash en MiniComandas

**User Story:** Como cajero, quiero ver los pedidos PedidosYA con pago en efectivo en MiniComandas con una etiqueta clara, para identificar rápidamente qué pedidos requieren cobro en efectivo.

#### Acceptance Criteria

1. WHEN MiniComandas carga un pedido con payment_method `pedidosya_cash`, THE MiniComandas SHALL mostrar la etiqueta "PedidosYA Efectivo" junto al ícono correspondiente.
2. WHEN MiniComandas carga un pedido con payment_method `pedidosya_cash` y payment_status `unpaid`, THE MiniComandas SHALL mostrar el botón "CONFIRMAR PAGO".
3. THE MiniComandas SHALL mostrar el monto total del pedido PedidosYA_Cash de forma visible en la tarjeta del pedido.

### Requirement 4: Modal de pago en efectivo al confirmar pago PedidosYA_Cash en MiniComandas

**User Story:** Como cajero, quiero que al confirmar el pago de un pedido PedidosYA con efectivo se abra un modal de cobro en efectivo con cálculo de vuelto, para cobrar correctamente al cliente y registrar el pago.

#### Acceptance Criteria

1. WHEN el cajero presiona "CONFIRMAR PAGO" en un pedido con payment_method `pedidosya_cash`, THE MiniComandas SHALL abrir el Cash_Modal mostrando el total a pagar del pedido.
2. THE Cash_Modal SHALL mostrar un campo de entrada para el monto recibido del cliente, un botón "Monto Exacto" que rellena el campo con el total del pedido, y botones de monto rápido ($5.000, $10.000, $20.000).
3. WHEN el cajero ingresa un monto mayor al total del pedido y presiona "Continuar", THE Cash_Modal SHALL mostrar una pantalla de confirmación con el desglose: total, monto recibido y vuelto a entregar.
4. WHEN el cajero ingresa un monto igual al total del pedido y presiona "Continuar", THE Cash_Modal SHALL proceder directamente a confirmar el pago sin mostrar pantalla de vuelto.
5. IF el cajero ingresa un monto menor al total del pedido y presiona "Continuar", THEN THE Cash_Modal SHALL mostrar un mensaje de error indicando el monto faltante.
6. IF el cajero ingresa un monto de cero o vacío y presiona "Continuar", THEN THE Cash_Modal SHALL mostrar un mensaje de error solicitando ingresar un monto o seleccionar "Monto Exacto".
7. THE Cash_Modal SHALL incluir un botón "Cancelar" que cierre el modal sin confirmar el pago.

### Requirement 5: Confirmación de pago y registro en caja para PedidosYA_Cash

**User Story:** Como cajero, quiero que al confirmar el pago en efectivo de un pedido PedidosYA se registre automáticamente el ingreso en caja, para que el saldo de caja refleje correctamente el efectivo recibido.

#### Acceptance Criteria

1. WHEN el cajero confirma el pago en el Cash_Modal de un pedido PedidosYA_Cash, THE Confirm_API SHALL actualizar el payment_status de la orden a `paid`.
2. WHEN el cajero confirma el pago en el Cash_Modal de un pedido PedidosYA_Cash, THE Confirm_API SHALL registrar un ingreso en Caja_Movimientos con el monto total del pedido, motivo "Venta PedidosYA Efectivo - Pedido #[order_number]" y order_reference igual al order_number.
3. WHEN el cajero confirma el pago en el Cash_Modal de un pedido PedidosYA_Cash, THE Confirm_API SHALL descontar el inventario de los productos del pedido.
4. WHEN el Confirm_API registra el ingreso en Caja_Movimientos, THE Confirm_API SHALL calcular el saldo_nuevo sumando el monto del pedido al saldo_anterior (último saldo registrado en Caja_Movimientos).
5. IF la confirmación de pago falla por error de base de datos, THEN THE Confirm_API SHALL revertir la transacción completa (rollback) y retornar un mensaje de error descriptivo.

### Requirement 6: Reportes y visualización de PedidosYA_Cash

**User Story:** Como administrador, quiero ver los pedidos PedidosYA con pago en efectivo diferenciados en los reportes de ventas y arqueo de caja, para tener trazabilidad completa del flujo de efectivo.

#### Acceptance Criteria

1. THE Arqueo_App SHALL mostrar una sección separada o incluir en la sección PedidosYA el desglose de pedidos PedidosYA Online y PedidosYA Efectivo con sus totales y cantidades.
2. THE Ventas_Detalle SHALL reconocer el payment_method `pedidosya_cash` y mostrarlo con la etiqueta "PedidosYA Efectivo" y un ícono diferenciado.
3. WHEN se filtra por método de pago en Ventas_Detalle, THE Ventas_Detalle SHALL incluir `pedidosya_cash` como opción de filtro.

### Requirement 7: Consistencia del flujo de pago en efectivo

**User Story:** Como cajero, quiero que el modal de pago en efectivo de PedidosYA_Cash funcione de forma idéntica al modal de efectivo existente en Checkout_App, para mantener una experiencia consistente.

#### Acceptance Criteria

1. THE Cash_Modal en MiniComandas SHALL utilizar el mismo formato de moneda chilena (separador de miles con punto, prefijo $) que el Cash_Modal de Checkout_App.
2. THE Cash_Modal en MiniComandas SHALL permitir entrada de monto por teclado con formato automático de miles.
3. THE Cash_Modal en MiniComandas SHALL soportar la tecla Enter para avanzar al paso de confirmación.
4. WHEN el pago se confirma exitosamente, THE MiniComandas SHALL recargar la lista de pedidos para reflejar el cambio de estado.
