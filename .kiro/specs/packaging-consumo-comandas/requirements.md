# Requirements Document

## Introduction

Registro de consumo de packaging (bolsas) desde MiniComandas (caja3) al momento de entregar o despachar un pedido. Reemplaza el tracking anterior basado en recetas de producto (1 bolsa por producto) por un registro manual real, ya que una bolsa puede contener múltiples productos. El cajero/cocinero indica cuántas bolsas de cada tipo usó mediante steppers inline antes de confirmar la entrega o despacho.

## Glossary

- **MiniComandas**: Componente React en caja3 (`MiniComandas.jsx`) que muestra los pedidos activos y permite gestionarlos (confirmar pago, despachar, entregar, anular).
- **Stepper**: Control numérico con botones "−" y "+" que permite incrementar o decrementar una cantidad entera desde 0.
- **Bolsa_Grande**: Ingrediente id=130, "BOLSA PAPEL CAFE CON MANILLA 36×20×39 CM", costo $203/unidad.
- **Bolsa_Mediana**: Ingrediente id=167, "BOLSA PAPEL CAFE CON MANILLA 30×12×32 CM", costo $137/unidad.
- **Packaging_Stepper_Area**: Zona de la tarjeta de pedido en MiniComandas ubicada justo encima de los botones de acción (ENTREGAR / DESPACHAR), que contiene los steppers de selección de bolsas.
- **Inventory_Transaction**: Registro en la tabla `inventory_transactions` que documenta un movimiento de stock (venta, compra, ajuste, devolución o consumo).
- **Consumption_Transaction**: Un `Inventory_Transaction` con `transaction_type = 'consumption'`, usado para registrar consumo de insumos no ligados a recetas.
- **Packaging_API**: Endpoint PHP en caja3 (`/api/register_packaging_consumption.php`) que recibe las cantidades de bolsas usadas y crea las transacciones de inventario correspondientes.
- **Order_Card**: Tarjeta visual en MiniComandas que representa un pedido activo con sus productos, información del cliente y botones de acción.

## Requirements

### Requirement 1: Packaging Stepper UI

**User Story:** Como cajero/cocinero, quiero ver controles de cantidad de bolsas en cada tarjeta de pedido, para poder indicar cuántas bolsas de cada tipo usé antes de entregar o despachar.

#### Acceptance Criteria

1. WHILE un pedido está en estado activo (no entregado ni cancelado), THE Packaging_Stepper_Area SHALL mostrar un stepper para Bolsa_Grande y un stepper para Bolsa_Mediana dentro de la Order_Card, posicionados justo encima de los botones de acción.
2. THE Packaging_Stepper_Area SHALL mostrar junto a cada stepper una imagen miniatura de la bolsa correspondiente usando las fotos disponibles en `/bolsa_deliverys/`.
3. THE Packaging_Stepper_Area SHALL inicializar ambos steppers en 0 para pedidos de tipo `pickup` (retiro local).
4. WHEN un pedido es de tipo `delivery`, THE Packaging_Stepper_Area SHALL pre-llenar el stepper de Bolsa_Grande con valor 1 como sugerencia.
5. THE Stepper SHALL permitir valores entre 0 y 10 inclusive.
6. WHEN el usuario presiona el botón "−" del Stepper y el valor actual es 0, THE Stepper SHALL mantener el valor en 0 sin decrementar.
7. WHEN el usuario presiona el botón "+" del Stepper y el valor actual es 10, THE Stepper SHALL mantener el valor en 10 sin incrementar.

### Requirement 2: Consumo de Packaging al Entregar (Local)

**User Story:** Como cajero, quiero que al entregar un pedido local se registre automáticamente el consumo de bolsas que seleccioné, para mantener el inventario de packaging actualizado.

#### Acceptance Criteria

1. WHEN el cajero presiona "✅ ENTREGAR" en un pedido de tipo `pickup`, THE MiniComandas SHALL enviar las cantidades de bolsas seleccionadas en los steppers junto con el `order_number` a la Packaging_API antes de cambiar el estado del pedido a `delivered`.
2. WHEN las cantidades de ambas bolsas son 0, THE MiniComandas SHALL proceder con la entrega sin llamar a la Packaging_API.
3. IF la llamada a la Packaging_API falla, THEN THE MiniComandas SHALL mostrar un mensaje de advertencia al cajero y proceder con la entrega del pedido de todas formas.

### Requirement 3: Consumo de Packaging al Despachar (Delivery Fase 1)

**User Story:** Como cajero/cocinero, quiero que al despachar un pedido delivery al rider se registre el consumo de bolsas, ya que es en ese momento cuando se empaqueta el pedido.

#### Acceptance Criteria

1. WHEN el cajero presiona "DESPACHAR A DELIVERY" en un pedido de tipo `delivery`, THE MiniComandas SHALL enviar las cantidades de bolsas seleccionadas en los steppers junto con el `order_number` a la Packaging_API antes de cambiar el estado del pedido a `ready`.
2. WHEN las cantidades de ambas bolsas son 0, THE MiniComandas SHALL proceder con el despacho sin llamar a la Packaging_API.
3. IF la llamada a la Packaging_API falla, THEN THE MiniComandas SHALL mostrar un mensaje de advertencia al cajero y proceder con el despacho del pedido de todas formas.

### Requirement 4: Packaging API — Registro de Transacciones de Consumo

**User Story:** Como sistema, quiero un endpoint que registre el consumo de bolsas como transacciones de inventario, para que el stock de packaging se descuente correctamente.

#### Acceptance Criteria

1. WHEN la Packaging_API recibe una solicitud con `order_number` y cantidades de bolsas, THE Packaging_API SHALL crear una Consumption_Transaction por cada tipo de bolsa cuya cantidad sea mayor a 0.
2. THE Packaging_API SHALL registrar cada Consumption_Transaction con `transaction_type = 'consumption'`, el `ingredient_id` correspondiente (130 para Bolsa_Grande, 167 para Bolsa_Mediana), la cantidad como valor negativo, la unidad `'unidad'`, el `previous_stock` y `new_stock` calculados, y el `order_reference` igual al `order_number` del pedido.
3. WHEN la Packaging_API crea una Consumption_Transaction, THE Packaging_API SHALL actualizar el `current_stock` del ingrediente correspondiente restando la cantidad consumida.
4. THE Packaging_API SHALL incluir en el campo `notes` de cada Consumption_Transaction un texto descriptivo: "Consumo packaging: {nombre_bolsa} x{cantidad} - Pedido {order_number}".
5. IF el `order_number` ya tiene transacciones de consumo de packaging registradas, THEN THE Packaging_API SHALL rechazar la solicitud con un mensaje de error para evitar duplicados.
6. IF el `current_stock` del ingrediente es menor que la cantidad solicitada, THEN THE Packaging_API SHALL registrar la transacción de todas formas (permitiendo stock negativo) y agregar una advertencia en la respuesta indicando stock insuficiente.
7. THE Packaging_API SHALL retornar un JSON con `success: true`, las transacciones creadas y cualquier advertencia de stock.

### Requirement 5: No Bloqueo del Flujo de Pedidos

**User Story:** Como cajero, quiero que la selección de bolsas sea opcional y no bloquee la entrega o despacho, para no retrasar la operación si olvido seleccionar bolsas.

#### Acceptance Criteria

1. WHEN ambos steppers tienen valor 0 y el cajero presiona ENTREGAR o DESPACHAR, THE MiniComandas SHALL completar la acción de entrega o despacho sin requerir selección de bolsas.
2. THE MiniComandas SHALL permitir entregar o despachar un pedido independientemente de los valores seleccionados en los steppers de packaging.

### Requirement 6: Packaging Stepper Solo en Fase Relevante (Delivery)

**User Story:** Como cajero, quiero ver los steppers de bolsas solo cuando corresponde empaquetar, para no confundirme con controles innecesarios.

#### Acceptance Criteria

1. WHILE un pedido de tipo `delivery` está en Fase 1 (estado `sent_to_kitchen` o `pending`, antes de despachar), THE Packaging_Stepper_Area SHALL ser visible en la Order_Card.
2. WHILE un pedido de tipo `delivery` está en Fase 2 (estado `ready`, esperando confirmación de entrega por el rider), THE Packaging_Stepper_Area SHALL estar oculta, ya que el empaquetado ya ocurrió en Fase 1.
3. WHILE un pedido de tipo `pickup` está activo, THE Packaging_Stepper_Area SHALL ser visible en la Order_Card.

### Requirement 7: Estado Visual del Stepper

**User Story:** Como cajero, quiero que los steppers sean compactos y claros visualmente, para poder usarlos rápidamente en el flujo de trabajo.

#### Acceptance Criteria

1. THE Packaging_Stepper_Area SHALL ocupar un máximo de 60px de altura para no desplazar excesivamente los botones de acción.
2. THE Stepper SHALL mostrar el nombre abreviado de la bolsa ("Grande" o "Mediana"), la imagen miniatura (24×24px), y los botones "−" y "+" con el valor numérico entre ellos.
3. WHEN el valor del Stepper es mayor a 0, THE Stepper SHALL resaltar visualmente el valor con un fondo de color diferenciado para indicar que hay bolsas seleccionadas.
