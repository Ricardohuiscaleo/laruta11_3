# Requirements Document

## Introduction

Mejoras a la sección Ventas del panel de administración de La Ruta 11 (mi3). Se corrige la visualización de fechas/horas para que siempre muestren zona horaria de Chile, se reemplaza la columna "Fuente" por "Fecha" en la tabla de transacciones, y se agrega un detalle expandible por orden que muestra ítems, costos, utilidad y consumo de ingredientes.

## Glossary

- **Sistema_Ventas**: Conjunto de componentes frontend (VentasPageContent.tsx) y backend (VentasService.php, VentasController.php) que gestionan la visualización de ventas en el panel de administración.
- **Tabla_Transacciones**: Tabla HTML/componente que lista las órdenes pagadas en la sección Ventas, tanto en vista desktop como mobile.
- **Detalle_Orden**: Panel expandible que se muestra al hacer clic en una fila de la Tabla_Transacciones, conteniendo ítems, costos y consumo de ingredientes de esa orden.
- **Zona_Chile**: Zona horaria America/Santiago, incluyendo manejo automático de horario de verano (CLST/CLT).
- **Consumo_Ingredientes**: Registro del uso de ingredientes por ítem vendido, calculado a partir de la tabla product_recipes y las transacciones de inventario (inventory_transactions).
- **Indicador_Stock**: Símbolo visual (✓ o ⚠) que indica si el stock del ingrediente se mantuvo en nivel adecuado (✓) o cayó bajo el mínimo (⚠) tras el consumo.

## Requirements


### Requirement 1: Visualización de fechas y horas en zona horaria de Chile

**User Story:** Como administrador, quiero que todas las fechas y horas en la sección Ventas se muestren en hora de Chile, para que los datos coincidan con la realidad operativa del restaurante.

#### Acceptance Criteria

1. THE Sistema_Ventas SHALL mostrar todas las fechas y horas en Zona_Chile (America/Santiago).
2. WHEN el backend retorna un campo `created_at` en una transacción, THE Sistema_Ventas SHALL convertir ese valor a Zona_Chile antes de mostrarlo al usuario.
3. WHILE el horario de verano de Chile esté activo, THE Sistema_Ventas SHALL aplicar el offset UTC-3 correspondiente a CLST de forma automática.
4. WHILE el horario estándar de Chile esté activo, THE Sistema_Ventas SHALL aplicar el offset UTC-4 correspondiente a CLT de forma automática.
5. IF el navegador del usuario tiene una zona horaria distinta a America/Santiago, THEN THE Sistema_Ventas SHALL mostrar las horas en Zona_Chile igualmente, ignorando la zona local del navegador.

### Requirement 2: Reemplazar columna "Fuente" por "Fecha" en la tabla de transacciones

**User Story:** Como administrador, quiero ver la fecha y hora de cada orden en la tabla de transacciones en lugar de la fuente, para identificar rápidamente cuándo se realizó cada venta.

#### Acceptance Criteria

1. THE Tabla_Transacciones SHALL mostrar una columna "Fecha" en lugar de la columna "Fuente" en la vista desktop.
2. WHEN una transacción se muestra en la columna "Fecha", THE Tabla_Transacciones SHALL mostrar la fecha y hora en formato "dd/MM HH:mm" en Zona_Chile.
3. THE Tabla_Transacciones SHALL eliminar la columna "Hora" existente, dado que la nueva columna "Fecha" ya incluye la hora.
4. WHEN una transacción se muestra en la vista mobile (cards), THE Tabla_Transacciones SHALL mostrar la fecha y hora en formato "dd/MM HH:mm" en Zona_Chile en lugar del badge de fuente.

### Requirement 3: Endpoint de detalle de orden

**User Story:** Como administrador, quiero que el backend provea los datos detallados de una orden, para que el frontend pueda mostrar el detalle expandible con ítems, costos y consumo de ingredientes.

#### Acceptance Criteria

1. WHEN el frontend solicita el detalle de una orden por su `order_number`, THE Sistema_Ventas SHALL retornar los datos de la orden incluyendo: número de orden, fecha/hora, nombre del cliente y método de pago.
2. WHEN el frontend solicita el detalle de una orden, THE Sistema_Ventas SHALL retornar la lista de ítems con: nombre del producto, cantidad, precio unitario, costo del ítem (`item_cost`) y utilidad por ítem (precio - costo).
3. WHEN el frontend solicita el detalle de una orden, THE Sistema_Ventas SHALL retornar el consumo de ingredientes por ítem, incluyendo: nombre del ingrediente, cantidad usada, stock antes del consumo, stock después del consumo.
4. WHEN el frontend solicita el detalle de una orden y un ingrediente cayó bajo su `min_stock_level` tras el consumo, THE Sistema_Ventas SHALL marcar ese ingrediente con un Indicador_Stock de advertencia (⚠).
5. WHEN el frontend solicita el detalle de una orden y un ingrediente se mantuvo en o sobre su `min_stock_level` tras el consumo, THE Sistema_Ventas SHALL marcar ese ingrediente con un Indicador_Stock de éxito (✓).
6. WHEN el frontend solicita el detalle de una orden, THE Sistema_Ventas SHALL retornar los totales: subtotal (suma de precios × cantidad), costo total (suma de costos × cantidad) y utilidad total (subtotal - costo total).
7. IF la orden no tiene registros en `inventory_transactions`, THEN THE Sistema_Ventas SHALL retornar una lista vacía de consumo de ingredientes para esa orden.
8. IF la orden no existe o el `order_number` es inválido, THEN THE Sistema_Ventas SHALL retornar un error HTTP 404 con un mensaje descriptivo.

### Requirement 4: Detalle expandible de orden en el frontend

**User Story:** Como administrador, quiero expandir una fila de la tabla de transacciones para ver el detalle completo de la orden, incluyendo ítems vendidos, costos y consumo de ingredientes, para analizar la rentabilidad de cada venta.

#### Acceptance Criteria

1. WHEN el usuario hace clic en una fila de la Tabla_Transacciones, THE Detalle_Orden SHALL expandirse mostrando un ícono chevron que indica el estado abierto/cerrado.
2. WHEN el Detalle_Orden está expandido, THE Detalle_Orden SHALL mostrar: fecha/hora de la orden en Zona_Chile, número de orden y método de pago.
3. WHEN el Detalle_Orden está expandido, THE Detalle_Orden SHALL mostrar una lista de ítems con: nombre del producto × cantidad, precio unitario, costo del ítem y utilidad del ítem.
4. WHEN el Detalle_Orden está expandido y existen registros de consumo de ingredientes, THE Detalle_Orden SHALL mostrar por cada ítem: nombre del ingrediente, cantidad usada, stock antes → stock después, y el Indicador_Stock correspondiente (✓ o ⚠).
5. WHEN el Detalle_Orden está expandido, THE Detalle_Orden SHALL mostrar al final: subtotal de la orden, costo total y utilidad total.
6. WHEN el usuario hace clic en una fila ya expandida, THE Detalle_Orden SHALL colapsar ocultando el contenido detallado.
7. WHILE el Detalle_Orden está cargando los datos del backend, THE Detalle_Orden SHALL mostrar un indicador de carga (spinner).
8. IF el backend retorna un error al solicitar el detalle, THEN THE Detalle_Orden SHALL mostrar un mensaje de error descriptivo dentro del área expandida.
9. WHEN el Detalle_Orden se muestra en vista mobile, THE Detalle_Orden SHALL adaptar su layout para pantallas pequeñas manteniendo toda la información legible.
10. THE Detalle_Orden SHALL usar etiquetas en español consistentes con el resto de la interfaz de La Ruta 11.

### Requirement 5: Datos de consumo de ingredientes desde recetas

**User Story:** Como administrador, quiero ver qué ingredientes se consumieron en cada venta, para controlar el inventario y detectar posibles problemas de stock.

#### Acceptance Criteria

1. WHEN el backend calcula el consumo de ingredientes para un ítem de orden, THE Sistema_Ventas SHALL consultar la tabla `product_recipes` para obtener los ingredientes y cantidades por unidad del producto.
2. WHEN el backend calcula el consumo de ingredientes para un ítem de orden, THE Sistema_Ventas SHALL multiplicar la cantidad de la receta por la cantidad vendida del ítem para obtener el consumo total por ingrediente.
3. WHEN existen registros en `inventory_transactions` para el `order_reference` y `order_item_id` correspondientes, THE Sistema_Ventas SHALL usar los valores `previous_stock` y `new_stock` de esas transacciones.
4. IF no existen registros en `inventory_transactions` para un ítem, THEN THE Sistema_Ventas SHALL calcular el consumo teórico basado en `product_recipes` sin datos de stock antes/después.
5. THE Sistema_Ventas SHALL mostrar el nombre del ingrediente, la unidad de medida y la cantidad consumida para cada ingrediente en el detalle de la orden.