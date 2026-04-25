# Requirements Document — Turnos y Nómina: Mejoras

## Introducción

Mejoras al sistema de turnos y nómina de mi3 (mi.laruta11.cl) que abordan tres problemas: (1) los reemplazos de turno se registran en la tabla `turnos` y el `LiquidacionService` los calcula correctamente, pero el `PayrollController` no expone el desglose al frontend, por lo que la nómina no muestra los montos de reemplazo; (2) el calendario de turnos ocupa demasiado espacio vertical y no muestra quién trabaja cada día ni los reemplazos sin hacer clic; y (3) el crédito R11 usado por trabajadores se descuenta automáticamente el día 1 via cron, pero no hay visibilidad del descuento pendiente en la liquidación durante el mes.

## Glosario

- **Sistema_Liquidacion**: Servicio backend (`LiquidacionService.php`) que calcula la liquidación mensual de cada trabajador, incluyendo sueldo base, reemplazos y ajustes. Ya calcula `totalReemplazando` y `totalReemplazados` internamente.
- **Sistema_Nomina**: Servicio backend (`NominaService.php`) que agrega las liquidaciones de todos los trabajadores activos por centro de costo (`ruta11`, `seguridad`).
- **API_Payroll**: Endpoint `GET /api/v1/admin/payroll` que retorna el resumen de nómina mensual al frontend. Actualmente el `PayrollController` agrega datos de ambos centros de costo pero descarta el desglose de reemplazos.
- **API_Shifts**: Endpoint `GET /api/v1/admin/shifts` que retorna los turnos del mes al frontend, combinando turnos manuales de BD con turnos dinámicos 4x4.
- **Vista_Nomina**: Componente frontend `NominaSection.tsx` que muestra la nómina mensual con tarjetas por trabajador. Actualmente muestra solo: base, días, reemplazos (número), ajustes y gran_total.
- **Vista_Turnos**: Componente frontend `TurnosSection.tsx` que muestra el calendario de turnos (grid 7 columnas en escritorio, scroll horizontal en móvil) y un panel de detalle diario con avatares.
- **Reemplazo**: Turno con tipo `reemplazo` o `reemplazo_seguridad` donde un trabajador sustituye a otro. El campo `personal_id` identifica al titular y `reemplazado_por` al reemplazante.
- **Monto_Reemplazo**: Valor monetario del reemplazo almacenado en `turnos.monto_reemplazo` ($30.000 para seguridad, $20.000 para R11).
- **Credito_R11**: Sistema de crédito interno donde trabajadores con `es_credito_r11 = 1` en la tabla `usuarios` pueden comprar a crédito. El campo `credito_r11_usado` acumula el monto consumido.
- **Cron_R11**: Comando `mi3:r11-auto-deduct` que se ejecuta el día 1 de cada mes a las 06:00 Chile. Crea un `ajustes_sueldo` negativo con `categoria_id` de la categoría `descuento_credito_r11` y resetea `credito_r11_usado` a 0.
- **Centro_Costo**: Agrupación contable de la nómina: `ruta11` (restaurante) o `seguridad` (cámaras de seguridad).
- **Turno_Dinamico**: Turno generado automáticamente por el algoritmo 4x4 sin registro en base de datos, identificado por `is_dynamic: true`.

## Requisitos

### Requisito 1: Exposición del desglose de reemplazos en la API de nómina

**User Story:** Como administrador, quiero que la API de nómina exponga el desglose de montos por reemplazos (ganados y descontados) que ya calcula el Sistema_Liquidacion, para que el frontend pueda mostrar esta información en la liquidación de cada trabajador.

#### Criterios de Aceptación

1. THE API_Payroll SHALL incluir en cada entrada de `resumen` los campos `total_reemplazando` (monto ganado por hacer reemplazos) y `total_reemplazado` (monto descontado por ser reemplazado), agregados de ambos centros de costo.
2. THE API_Payroll SHALL incluir en cada entrada de `resumen` los campos `reemplazos_realizados` y `reemplazos_recibidos` como arrays con el detalle agrupado por persona (nombre, días, monto, pago_por), concatenados de ambos centros de costo.
3. THE API_Payroll SHALL calcular el campo `gran_total` como `sueldo_base + total_reemplazando - total_reemplazado + ajustes_total` para cada trabajador.
4. WHEN un reemplazo tiene `pago_por` igual a `empresa`, THE Sistema_Liquidacion SHALL incluir el `monto_reemplazo` en el `total_reemplazando` del reemplazante.
5. WHEN un reemplazo tiene `pago_por` igual a `empresa` o `empresa_adelanto`, THE Sistema_Liquidacion SHALL incluir el `monto_reemplazo` en el `total_reemplazado` del titular reemplazado.
6. WHEN un reemplazo no tiene `monto_reemplazo` definido (valor nulo), THE Sistema_Liquidacion SHALL usar $20.000 como monto por defecto.

### Requisito 2: Visualización de reemplazos en la tarjeta de nómina

**User Story:** Como administrador, quiero ver en la tarjeta de nómina de cada trabajador los montos de reemplazos ganados y descontados, para entender cómo se compone el total de la liquidación.

#### Criterios de Aceptación

1. WHEN un trabajador tiene `total_reemplazando` mayor a cero, THE Vista_Nomina SHALL mostrar el monto con formato CLP en color verde con un prefijo "+" en la fila de datos de la tarjeta.
2. WHEN un trabajador tiene `total_reemplazado` mayor a cero, THE Vista_Nomina SHALL mostrar el monto con formato CLP en color rojo con un prefijo "-" en la fila de datos de la tarjeta.
3. WHEN el administrador hace clic en una tarjeta de trabajador, THE Vista_Nomina SHALL expandir la tarjeta mostrando el desglose completo: sueldo base por centro de costo, días trabajados, detalle de reemplazos realizados (nombre del titular, días del mes, monto), detalle de reemplazos recibidos (nombre del reemplazante, días del mes, monto), lista de ajustes (concepto y monto), y crédito R11 pendiente si aplica.
4. WHEN la tarjeta está expandida y el administrador hace clic nuevamente, THE Vista_Nomina SHALL colapsar la tarjeta al estado compacto original.

### Requisito 3: Calendario de turnos compacto con avatares y reemplazos visibles

**User Story:** Como administrador, quiero un calendario de turnos más compacto que muestre de un vistazo quién trabaja cada día y los reemplazos, para gestionar los turnos sin necesidad de hacer scroll excesivo.

#### Criterios de Aceptación

1. THE Vista_Turnos SHALL mostrar en cada celda del calendario de escritorio los avatares miniatura (diámetro máximo 24px) de los trabajadores asignados a ese día, separados visualmente entre sección R11 y sección Seguridad.
2. THE Vista_Turnos SHALL usar una altura mínima de celda de 72px en el calendario de escritorio, reducida desde los 100px actuales.
3. WHEN un día tiene al menos un reemplazo registrado, THE Vista_Turnos SHALL mostrar un indicador visual en la celda del calendario (borde lateral de color naranja o ícono de intercambio de 12px) que distinga ese día de los días sin reemplazos.
4. WHEN el usuario hace clic en una celda del calendario, THE Vista_Turnos SHALL mostrar el panel de detalle del día seleccionado con la lista completa de trabajadores, sus roles, y para cada reemplazo: el nombre del titular tachado, una flecha, el nombre del reemplazante y el monto.
5. THE Vista_Turnos SHALL mostrar en la vista móvil (scroll horizontal) un punto de color naranja en las tarjetas de día que tengan al menos un reemplazo registrado.
6. WHEN no hay turnos asignados para un día, THE Vista_Turnos SHALL mostrar la celda con fondo gris claro y sin avatares.

### Requisito 4: Visualización de descuento R11 pendiente en la liquidación

**User Story:** Como administrador, quiero ver en la liquidación de cada trabajador el monto de crédito R11 pendiente de descuento, para saber cuánto se descontará automáticamente el día 1 del mes siguiente.

#### Criterios de Aceptación

1. WHEN el Sistema_Liquidacion calcula la liquidación de un trabajador cuyo `personal.user_id` apunta a un usuario con `es_credito_r11 = 1` y `credito_r11_usado > 0`, THE API_Payroll SHALL incluir un campo `credito_r11_pendiente` con el valor de `credito_r11_usado` de la tabla `usuarios`.
2. WHEN ya existe un registro en `ajustes_sueldo` con `categoria_id` correspondiente a `descuento_credito_r11` para el trabajador y el mes consultado, THE API_Payroll SHALL retornar `credito_r11_pendiente` como 0 para ese trabajador, indicando que el descuento ya fue aplicado.
3. THE Vista_Nomina SHALL mostrar el campo `credito_r11_pendiente` con un ícono de tarjeta de crédito y la etiqueta "Crédito R11 pendiente" debajo del `gran_total` cuando el valor sea mayor a cero.
4. THE Vista_Nomina SHALL mostrar el `credito_r11_pendiente` como información separada del `gran_total`, con texto explicativo "Se descontará el día 1" en color gris.
5. IF el trabajador no tiene `personal.user_id` vinculado a un usuario con `es_credito_r11 = 1`, THEN THE API_Payroll SHALL no incluir el campo `credito_r11_pendiente` en la respuesta para ese trabajador.
