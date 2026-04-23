# Requirements Document — Inventario Financiero Real

## Introducción

La Ruta 11 es un foodtruck en Chile que opera con un sistema digital (app3, caja3, mi3) compartiendo una base de datos MySQL. Durante una auditoría financiera se detectaron problemas graves: inventario inflado a $1.5M (imposible para un foodtruck), capital de trabajo roto (saldo_inicial=0, ingresos_ventas=0), consumibles como gas y limpieza que se compran pero nunca se consumen, y un Estado de Resultados incompleto que solo considera CMV de ingredientes y nómina como gastos. Este spec corrige el sistema financiero completo para reflejar la realidad operativa del negocio.

## Glosario

- **Sistema_Inventario**: Módulo que gestiona el stock de ingredientes y productos en la tabla `ingredients`, incluyendo transacciones en `inventory_transactions`.
- **Sistema_Consumibles**: Nuevo subsistema para gestionar ítems no-receta (Gas, Limpieza, Servicios) que se compran pero no se deducen automáticamente por ventas.
- **Sistema_Cierre_Diario**: Proceso automatizado que consolida ventas (desde `tuu_orders`), movimientos de caja (`caja_movimientos`), y compras (`compras`) en un registro diario de capital de trabajo (`capital_trabajo`).
- **Sistema_PnL**: Módulo de Estado de Resultados en mi3 (`DashboardController.php` + `DashboardSection.tsx`) que calcula Ingresos, CMV, Gastos Operacionales y Resultado Neto.
- **Auditoría_Inventario**: Proceso de conteo físico donde un operador registra las cantidades reales de stock y el sistema ajusta las diferencias.
- **Consumible**: Ingrediente de categoría Gas, Limpieza o Servicios que no tiene receta asociada y se consume operacionalmente (no por venta de productos).
- **CMV**: Costo de Mercadería Vendida — costo de ingredientes consumidos por las ventas del período.
- **OPEX**: Gastos operacionales — nómina, gas, limpieza, packaging descartado, y otros costos no vinculados directamente a recetas.
- **Cierre_Diario**: Registro en `capital_trabajo` que resume: saldo inicial + ingresos por ventas − egresos por compras − egresos por gastos = saldo final.
- **Turno**: Período operativo de La Ruta 11 que va de 17:00 a 04:00 del día siguiente (hora Chile, UTC-3).
- **TUU**: Proveedor de terminales de pago (Webpay, tarjeta, transferencia) integrado en el sistema de órdenes.

## Requisitos

### Requisito 1: Auditoría y Reset de Inventario

**User Story:** Como administrador, quiero realizar un conteo físico del inventario y ajustar el stock del sistema a la realidad, para eliminar el inventario fantasma acumulado.

#### Criterios de Aceptación

1. WHEN el administrador solicita una auditoría de inventario, THE Sistema_Inventario SHALL generar una lista de todos los ingredientes activos con su stock actual del sistema y unidad de medida.
2. WHEN el administrador envía las cantidades del conteo físico, THE Sistema_Inventario SHALL calcular la diferencia entre el stock del sistema y el stock real para cada ingrediente.
3. WHEN el administrador confirma el ajuste de inventario, THE Sistema_Inventario SHALL actualizar el campo `current_stock` de cada ingrediente al valor del conteo físico.
4. WHEN se aplica un ajuste de inventario, THE Sistema_Inventario SHALL registrar una transacción de tipo `adjustment` en `inventory_transactions` para cada ingrediente modificado, incluyendo `previous_stock`, `new_stock` y una nota indicando "Auditoría física".
5. WHEN se aplica un ajuste de inventario, THE Sistema_Inventario SHALL recalcular el `stock_quantity` de todos los productos con receta que usen los ingredientes ajustados.
6. THE Sistema_Inventario SHALL mostrar un resumen del ajuste con: cantidad de ítems modificados, valor total del inventario antes del ajuste, valor total después del ajuste, y diferencia monetaria.

### Requisito 2: Consumo de Ítems No-Receta (Gas, Limpieza, Servicios)

**User Story:** Como administrador, quiero registrar el consumo de gas, productos de limpieza y otros consumibles operacionales, para que el inventario refleje el uso real y los costos aparezcan en el P&L.

#### Criterios de Aceptación

1. THE Sistema_Consumibles SHALL identificar como consumibles todos los ingredientes activos cuya categoría sea "Gas", "Limpieza" o "Servicios".
2. WHEN el administrador selecciona un consumible y registra una cantidad consumida, THE Sistema_Consumibles SHALL reducir el `current_stock` del ingrediente por la cantidad indicada.
3. WHEN se registra un consumo, THE Sistema_Consumibles SHALL crear una transacción de tipo `consumption` en `inventory_transactions` con el `ingredient_id`, la cantidad negativa, `previous_stock`, `new_stock` y una nota descriptiva.
4. WHEN se registra un consumo, THE Sistema_Consumibles SHALL registrar el costo del consumo (cantidad × `cost_per_unit`) como gasto operacional del día en la categoría correspondiente (gas, limpieza, servicios).
5. IF un consumo reduce el `current_stock` por debajo de cero, THEN THE Sistema_Consumibles SHALL rechazar la operación e informar el stock disponible actual.
6. THE Sistema_Consumibles SHALL proporcionar una vista que liste todos los consumibles con su stock actual, costo unitario, valor en inventario y un botón para registrar consumo.

### Requisito 3: Categorización Extendida de Compras

**User Story:** Como administrador, quiero que las compras se clasifiquen con categorías más específicas que "ingredientes/insumos/otros", para distinguir gas de packaging de limpieza en los reportes financieros.

#### Criterios de Aceptación

1. THE Sistema_Inventario SHALL soportar las siguientes categorías de compra: `ingredientes`, `insumos`, `gas`, `limpieza`, `packaging`, `servicios`, `equipamiento`, `otros`.
2. WHEN se registra una compra, THE Sistema_Inventario SHALL asignar automáticamente la categoría de compra basándose en la categoría del ingrediente asociado: ingredientes con categoría "Gas" se clasifican como compra tipo `gas`, ingredientes con categoría "Limpieza" como `limpieza`, ingredientes con categoría "Packaging" como `packaging`, ingredientes con categoría "Servicios" como `servicios`.
3. WHEN se registra una compra sin ingrediente asociado, THE Sistema_Inventario SHALL permitir al usuario seleccionar manualmente la categoría de compra de la lista extendida.
4. THE Sistema_Inventario SHALL mantener compatibilidad retroactiva con los registros existentes que usan las categorías `ingredientes`, `insumos` y `otros`.

### Requisito 4: Cierre Diario Automatizado de Capital de Trabajo

**User Story:** Como administrador, quiero que el sistema genere automáticamente un cierre diario que diga "empecé con X, vendí Y, gasté Z, terminé con W", para tener visibilidad real del flujo de caja.

#### Criterios de Aceptación

1. WHEN finaliza un turno operativo (04:00 hora Chile), THE Sistema_Cierre_Diario SHALL generar automáticamente un registro en `capital_trabajo` para la fecha del turno.
2. THE Sistema_Cierre_Diario SHALL calcular `saldo_inicial` como el `saldo_final` del día anterior en `capital_trabajo`.
3. THE Sistema_Cierre_Diario SHALL calcular `ingresos_ventas` sumando el campo `installment_amount` de todas las órdenes en `tuu_orders` con `payment_status='paid'` dentro del rango horario del turno (17:00 a 04:00 UTC-3), restando `delivery_fee`.
4. THE Sistema_Cierre_Diario SHALL calcular `egresos_compras` sumando el `monto_total` de todas las compras en `compras` cuya `fecha_compra` corresponda al día del turno.
5. THE Sistema_Cierre_Diario SHALL calcular `egresos_gastos` sumando todos los consumos registrados en `inventory_transactions` de tipo `consumption` del día, más los retiros de caja registrados en `caja_movimientos` de tipo `retiro` del día.
6. THE Sistema_Cierre_Diario SHALL calcular `saldo_final` como: `saldo_inicial` + `ingresos_ventas` − `egresos_compras` − `egresos_gastos`.
7. WHEN el administrador solicita un cierre manual para una fecha específica, THE Sistema_Cierre_Diario SHALL recalcular y actualizar el registro de `capital_trabajo` para esa fecha.
8. IF no existe un registro de `capital_trabajo` para el día anterior, THEN THE Sistema_Cierre_Diario SHALL usar saldo_inicial = 0 y registrar una advertencia en las notas.

### Requisito 5: Ingresos Completos en Caja (Todos los Medios de Pago)

**User Story:** Como administrador, quiero que el sistema registre como ingreso todas las ventas independientemente del medio de pago (efectivo, tarjeta, transferencia, crédito RL6), para que el cierre diario refleje los ingresos reales.

#### Criterios de Aceptación

1. THE Sistema_Cierre_Diario SHALL incluir en `ingresos_ventas` todas las órdenes pagadas de `tuu_orders` sin importar el `payment_method` (webpay, transfer, card, cash, pedidosya, rl6_credit).
2. THE Sistema_Cierre_Diario SHALL desglosar los ingresos por método de pago en el registro diario, almacenando un campo JSON o columnas adicionales con el monto por cada método.
3. WHEN se genera el cierre diario, THE Sistema_Cierre_Diario SHALL incluir los ingresos en efectivo registrados en `caja_movimientos` de tipo `ingreso` que no tengan `order_reference` (ingresos manuales no vinculados a órdenes TUU).
4. THE Sistema_Cierre_Diario SHALL excluir las órdenes con `payment_status` distinto de `paid` del cálculo de ingresos.

### Requisito 6: Estado de Resultados (P&L) con Todos los Costos Operacionales

**User Story:** Como administrador, quiero ver un Estado de Resultados mensual que incluya todos los costos reales del negocio (CMV, nómina, gas, limpieza, packaging, mermas), para conocer la rentabilidad real de La Ruta 11.

#### Criterios de Aceptación

1. THE Sistema_PnL SHALL calcular los Ingresos Netos sumando `installment_amount` menos `delivery_fee` de todas las órdenes pagadas del mes.
2. THE Sistema_PnL SHALL calcular el CMV (Costo de Mercadería Vendida) sumando el `item_cost × quantity` de `tuu_order_items` para todas las órdenes pagadas del mes.
3. THE Sistema_PnL SHALL calcular el Margen Bruto como Ingresos Netos menos CMV.
4. THE Sistema_PnL SHALL incluir en Gastos Operacionales las siguientes líneas desglosadas: Nómina Equipo (desde tabla `personal`, excluyendo rol dueño), Gas (consumos de categoría Gas del mes), Limpieza (consumos de categoría Limpieza del mes), Mermas (costo total de registros en tabla `mermas` del mes), Otros Gastos Operacionales (consumos de categoría Servicios del mes).
5. THE Sistema_PnL SHALL calcular el Total OPEX como la suma de todas las líneas de Gastos Operacionales.
6. THE Sistema_PnL SHALL calcular el Resultado Neto como Margen Bruto menos Total OPEX.
7. THE Sistema_PnL SHALL mostrar el porcentaje sobre ventas para cada línea del estado de resultados (CMV%, Margen Bruto%, cada línea OPEX%, Resultado Neto%).
8. THE Sistema_PnL SHALL mostrar la Meta Mensual (punto de equilibrio) calculada como Total OPEX dividido por el porcentaje de Margen Bruto.

### Requisito 7: Resumen Financiero Mensual

**User Story:** Como administrador, quiero ver un resumen financiero mensual consolidado que muestre la evolución del capital de trabajo día a día, para entender el flujo de dinero del negocio.

#### Criterios de Aceptación

1. THE Sistema_Cierre_Diario SHALL proporcionar una vista mensual que liste todos los registros de `capital_trabajo` del mes seleccionado, ordenados por fecha.
2. WHEN el administrador selecciona un mes, THE Sistema_Cierre_Diario SHALL mostrar para cada día: fecha, saldo inicial, ingresos por ventas, egresos por compras, egresos por gastos y saldo final.
3. THE Sistema_Cierre_Diario SHALL mostrar totales del mes: total ingresos, total egresos compras, total egresos gastos, y variación neta del saldo (saldo final último día menos saldo inicial primer día).
4. WHEN existen días sin registro de cierre, THE Sistema_Cierre_Diario SHALL marcar esos días como "Sin cierre" en la vista mensual.

### Requisito 8: Migración y Corrección de Datos Históricos

**User Story:** Como administrador, quiero que el sistema corrija los registros históricos rotos de capital de trabajo y reclasifique las compras existentes, para que los reportes reflejen datos coherentes desde el inicio.

#### Criterios de Aceptación

1. WHEN se ejecuta la migración, THE Sistema_Inventario SHALL reclasificar las compras existentes de tipo `otros` asignando la nueva categoría basándose en el proveedor y los ingredientes asociados: compras de proveedor "Abastible" se reclasifican como `gas`, compras con ingredientes de categoría "Limpieza" como `limpieza`, compras con ingredientes de categoría "Packaging" como `packaging`.
2. WHEN se ejecuta la migración, THE Sistema_Cierre_Diario SHALL recalcular todos los registros de `capital_trabajo` existentes usando la lógica del cierre diario automatizado (Requisito 4), corrigiendo `saldo_inicial` e `ingresos_ventas` que actualmente están en cero.
3. THE Sistema_Inventario SHALL agregar el valor `consumption` al enum `transaction_type` de la tabla `inventory_transactions` para soportar el registro de consumos operacionales.
4. THE Sistema_Inventario SHALL ampliar el enum `tipo_compra` de la tabla `compras` para incluir los valores: `gas`, `limpieza`, `packaging`, `servicios`.
