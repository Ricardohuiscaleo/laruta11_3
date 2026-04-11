# Documento de Requerimientos — mi3 Worker Dashboard v2

## Introducción

Este spec define las mejoras al dashboard del trabajador en mi3 (mi.laruta11.cl). Actualmente el dashboard muestra turnos del día, crédito R11 y notificaciones. El usuario necesita:

1. Que los nuevos trabajadores tengan un sueldo base por defecto de $300.000
2. Un sistema de préstamos/adelantos de sueldo donde los trabajadores solicitan y el admin aprueba
3. Que el dashboard de inicio muestre: sueldo, préstamos, descuentos y reemplazos
4. Una sección dedicada "Préstamos" para solicitar y ver préstamos
5. Gestión de reemplazos desde el lado del trabajador

La arquitectura existente se mantiene: frontend Next.js en `mi3/frontend` y backend Laravel en `mi3/backend`, conectados a la BD MySQL compartida.

## Glosario

- **Sistema_Mi3**: Aplicación web mi3 compuesta por frontend (mi.laruta11.cl) y backend (api-mi3.laruta11.cl)
- **Trabajador**: Persona registrada en la tabla `personal` con `activo = 1`
- **Administrador**: Trabajador con rol 'administrador' o 'dueño' en la tabla `personal`
- **Tabla_Personal**: Tabla `personal` de la BD MySQL compartida
- **Tabla_Prestamos**: Nueva tabla `prestamos` para solicitudes de préstamos de sueldo
- **Tabla_Ajustes_Sueldo**: Tabla `ajustes_sueldo` de la BD MySQL compartida
- **Tabla_Turnos**: Tabla `turnos` de la BD MySQL compartida
- **Tabla_Ajustes_Categorias**: Tabla `ajustes_categorias` de la BD MySQL compartida
- **Préstamo**: Solicitud de adelanto de sueldo que un Trabajador crea y un Administrador aprueba o rechaza. Al aprobarse, se registra como ajuste negativo en Tabla_Ajustes_Sueldo
- **Reemplazo**: Turno donde un Trabajador cubre a otro, con monto asociado. Puede ser tipo 'reemplazo' o 'reemplazo_seguridad' en Tabla_Turnos
- **Descuento**: Ajuste negativo en Tabla_Ajustes_Sueldo (adelantos, multas, descuento crédito R11, cuotas de préstamo)
- **Liquidación**: Cálculo mensual del sueldo: sueldo base + reemplazos realizados - reemplazos recibidos + ajustes
- **Dashboard_Inicio**: Página principal del trabajador en `/dashboard` que muestra resumen de sueldo, préstamos, descuentos y reemplazos
- **Sueldo_Base_Defecto**: Valor de $300.000 CLP que se asigna automáticamente a nuevos trabajadores

## Requerimientos

### Requerimiento 1: Sueldo Base por Defecto para Nuevos Trabajadores

**User Story:** Como Administrador, quiero que al crear un nuevo trabajador se asigne automáticamente un sueldo base de $300.000, para que no tenga que recordar ingresarlo manualmente cada vez.

#### Criterios de Aceptación

1. WHEN un Administrador crea un nuevo trabajador sin especificar sueldo base, THE Sistema_Mi3 SHALL asignar el valor de $300.000 CLP como sueldo base para el rol principal del trabajador (sueldo_base_cajero, sueldo_base_planchero, sueldo_base_admin o sueldo_base_seguridad según corresponda).
2. WHEN un Administrador crea un nuevo trabajador especificando un sueldo base diferente, THE Sistema_Mi3 SHALL usar el valor proporcionado en lugar del valor por defecto de $300.000.
3. WHEN el formulario de creación de personal se muestra al Administrador, THE Sistema_Mi3 SHALL pre-rellenar el campo de sueldo base con $300.000 para cada rol seleccionado.
4. THE API `POST /api/v1/admin/personal` SHALL aplicar el valor por defecto de $300.000 en el backend cuando el campo de sueldo base del rol correspondiente sea nulo o cero.

### Requerimiento 2: Sistema de Préstamos — Modelo de Datos

**User Story:** Como Administrador técnico, quiero que exista una tabla de préstamos en la base de datos, para que el sistema pueda almacenar solicitudes de préstamos con su estado y condiciones de pago.

#### Criterios de Aceptación

1. THE Sistema_Mi3 SHALL crear la tabla `prestamos` con columnas: `id` (PK AUTO_INCREMENT), `personal_id` (INT NOT NULL FK → personal), `monto_solicitado` (DECIMAL 10,2 NOT NULL), `monto_aprobado` (DECIMAL 10,2 NULL), `motivo` (VARCHAR 255), `cuotas` (INT NOT NULL DEFAULT 1), `cuotas_pagadas` (INT NOT NULL DEFAULT 0), `estado` (ENUM 'pendiente','aprobado','rechazado','pagado','cancelado' DEFAULT 'pendiente'), `aprobado_por` (INT NULL FK → personal), `fecha_aprobacion` (TIMESTAMP NULL), `fecha_inicio_descuento` (DATE NULL), `notas_admin` (TEXT NULL), `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP), `updated_at` (TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP).
2. THE Sistema_Mi3 SHALL crear índices en la tabla `prestamos` para las columnas `personal_id`, `estado` y `created_at`.
3. THE Sistema_Mi3 SHALL agregar la categoría 'prestamo' a Tabla_Ajustes_Categorias si no existe, con nombre "Cuota Préstamo", slug "prestamo", ícono "💰" y signo por defecto "-".

### Requerimiento 3: Solicitud de Préstamos por el Trabajador

**User Story:** Como Trabajador, quiero solicitar un préstamo/adelanto de sueldo desde mi3, para que pueda obtener dinero antes de fin de mes cuando lo necesite.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a la sección "Préstamos" en `/dashboard/prestamos`, THE Sistema_Mi3 SHALL mostrar un botón "Solicitar Préstamo" y la lista de préstamos del Trabajador ordenados por fecha descendente.
2. WHEN un Trabajador solicita un préstamo, THE Sistema_Mi3 SHALL mostrar un formulario con: monto solicitado (campo numérico obligatorio), número de cuotas (selector: 1, 2 o 3 cuotas) y motivo (campo de texto opcional).
3. WHEN un Trabajador envía una solicitud de préstamo, THE Sistema_Mi3 SHALL crear un registro en Tabla_Prestamos con estado 'pendiente' vía `POST /api/v1/worker/loans`.
4. WHEN un Trabajador envía una solicitud de préstamo, THE Sistema_Mi3 SHALL validar que el monto solicitado sea mayor a $0 y menor o igual al sueldo base del Trabajador.
5. IF un Trabajador tiene un préstamo con estado 'aprobado' (aún no pagado completamente), THEN THE Sistema_Mi3 SHALL impedir la creación de una nueva solicitud y mostrar un mensaje indicando que debe completar el préstamo activo antes de solicitar otro.
6. WHEN una solicitud de préstamo es creada, THE Sistema_Mi3 SHALL enviar una notificación al Administrador vía la tabla `notificaciones_mi3` con tipo 'sistema' y título "Nueva solicitud de préstamo".

### Requerimiento 4: Gestión de Préstamos por el Administrador

**User Story:** Como Administrador, quiero aprobar o rechazar solicitudes de préstamos de los trabajadores, para que pueda controlar los adelantos de sueldo del equipo.

#### Criterios de Aceptación

1. WHEN un Administrador accede a la gestión de préstamos, THE Sistema_Mi3 SHALL listar todas las solicitudes de préstamos ordenadas por estado (pendientes primero) y fecha, mostrando: nombre del trabajador, monto solicitado, cuotas, motivo, estado y fecha de solicitud.
2. WHEN un Administrador aprueba un préstamo, THE Sistema_Mi3 SHALL actualizar el estado a 'aprobado', registrar el monto aprobado (que puede diferir del solicitado), la fecha de aprobación, la fecha de inicio de descuento (mes siguiente por defecto) y notas opcionales del admin.
3. WHEN un Administrador aprueba un préstamo, THE Sistema_Mi3 SHALL crear un ajuste positivo en Tabla_Ajustes_Sueldo con categoría 'prestamo', concepto "Préstamo aprobado" y el monto aprobado, para que se refleje como ingreso en la liquidación del mes actual.
4. WHEN un Administrador rechaza un préstamo, THE Sistema_Mi3 SHALL actualizar el estado a 'rechazado' y registrar notas opcionales del admin.
5. WHEN un préstamo es aprobado o rechazado, THE Sistema_Mi3 SHALL crear una notificación para el Trabajador solicitante con el resultado de la solicitud.

### Requerimiento 5: Descuento Automático de Cuotas de Préstamo

**User Story:** Como Administrador, quiero que las cuotas de los préstamos se descuenten automáticamente de la liquidación mensual, para que no tenga que crear ajustes manuales cada mes.

#### Criterios de Aceptación

1. WHEN llega el día 1 del mes, THE Sistema_Mi3 SHALL ejecutar un proceso que consulte todos los préstamos con estado 'aprobado' y `cuotas_pagadas < cuotas` cuya `fecha_inicio_descuento` sea menor o igual al mes actual.
2. WHEN un préstamo tiene cuotas pendientes, THE Sistema_Mi3 SHALL crear un ajuste negativo en Tabla_Ajustes_Sueldo con categoría 'prestamo', concepto "Cuota préstamo [N/total] - [mes]" y monto igual a `monto_aprobado / cuotas`, redondeado al peso.
3. WHEN se descuenta una cuota de préstamo, THE Sistema_Mi3 SHALL incrementar `cuotas_pagadas` en 1 en Tabla_Prestamos.
4. WHEN `cuotas_pagadas` alcanza el valor de `cuotas`, THE Sistema_Mi3 SHALL actualizar el estado del préstamo a 'pagado'.
5. THE Sistema_Mi3 SHALL ejecutar el descuento de cuotas de préstamo dentro de una transacción de base de datos para garantizar consistencia entre Tabla_Prestamos y Tabla_Ajustes_Sueldo.

### Requerimiento 6: Dashboard de Inicio Rediseñado

**User Story:** Como Trabajador, quiero ver en mi pantalla de inicio un resumen de mi sueldo, préstamos activos, descuentos del mes y reemplazos, para que tenga visibilidad completa de mi situación laboral actual.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a `/dashboard`, THE Sistema_Mi3 SHALL mostrar cuatro tarjetas de resumen: Sueldo (total liquidación del mes actual), Préstamos (monto pendiente de préstamo activo o "$0" si no tiene), Descuentos (suma de ajustes negativos del mes) y Reemplazos (cantidad de reemplazos realizados y recibidos del mes).
2. WHEN un Trabajador tiene un préstamo con estado 'aprobado', THE Sistema_Mi3 SHALL mostrar en la tarjeta de Préstamos: monto pendiente por pagar, cuotas restantes y monto de la próxima cuota.
3. WHEN un Trabajador tiene descuentos en el mes actual, THE Sistema_Mi3 SHALL mostrar en la tarjeta de Descuentos: el total de descuentos y un desglose por categoría (adelantos, multas, crédito R11, cuotas préstamo).
4. WHEN un Trabajador tiene reemplazos en el mes actual, THE Sistema_Mi3 SHALL mostrar en la tarjeta de Reemplazos: cantidad de reemplazos realizados (con monto ganado) y cantidad de reemplazos recibidos (con monto descontado).
5. THE Sistema_Mi3 SHALL obtener los datos del dashboard de inicio mediante un endpoint `GET /api/v1/worker/dashboard-summary` que retorne sueldo, préstamos, descuentos y reemplazos del mes actual en una sola llamada.
6. THE Sistema_Mi3 SHALL mantener las secciones existentes de turnos del día y notificaciones debajo de las cuatro tarjetas de resumen.

### Requerimiento 7: Sección de Préstamos del Trabajador

**User Story:** Como Trabajador, quiero una sección dedicada donde pueda ver el historial de mis préstamos y el estado de cada uno, para que pueda hacer seguimiento de mis solicitudes y pagos.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a `/dashboard/prestamos`, THE Sistema_Mi3 SHALL mostrar la lista de préstamos del Trabajador con: monto solicitado, monto aprobado (si aplica), cuotas totales, cuotas pagadas, estado (pendiente/aprobado/rechazado/pagado), fecha de solicitud y notas del admin (si existen).
2. WHEN un Trabajador tiene un préstamo con estado 'aprobado', THE Sistema_Mi3 SHALL mostrar una barra de progreso visual indicando cuotas pagadas vs cuotas totales.
3. WHEN un Trabajador tiene un préstamo con estado 'aprobado', THE Sistema_Mi3 SHALL mostrar el monto de la próxima cuota y la fecha estimada de descuento (primer día del mes siguiente).
4. THE Sistema_Mi3 SHALL obtener los préstamos del trabajador vía `GET /api/v1/worker/loans` y retornar la lista ordenada por `created_at` descendente.
5. WHEN un Trabajador no tiene préstamos, THE Sistema_Mi3 SHALL mostrar un estado vacío con mensaje "No tienes préstamos" y el botón de solicitar.

### Requerimiento 8: Gestión de Reemplazos desde el Trabajador

**User Story:** Como Trabajador, quiero ver mis reemplazos del mes y solicitar que alguien me cubra, para que pueda gestionar mis ausencias de forma autónoma.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a `/dashboard/reemplazos`, THE Sistema_Mi3 SHALL mostrar dos secciones: "Reemplazos que hice" (turnos donde el Trabajador fue reemplazante, con nombre del titular y monto ganado) y "Me reemplazaron" (turnos donde otro cubrió al Trabajador, con nombre del reemplazante y monto descontado).
2. WHEN un Trabajador accede a la sección de reemplazos, THE Sistema_Mi3 SHALL mostrar un resumen mensual con: total ganado por reemplazos realizados, total descontado por reemplazos recibidos y balance neto.
3. THE Sistema_Mi3 SHALL permitir al Trabajador navegar entre meses para consultar reemplazos históricos.
4. THE Sistema_Mi3 SHALL obtener los datos de reemplazos vía `GET /api/v1/worker/replacements?mes=YYYY-MM` filtrando los turnos del mes donde el Trabajador es titular con tipo 'reemplazo'/'reemplazo_seguridad' o donde es reemplazante.
5. WHEN un Trabajador necesita solicitar un reemplazo, THE Sistema_Mi3 SHALL redirigir a la sección de "Solicitudes de Cambio" existente (`/dashboard/cambios`) que ya permite solicitar cambios de turno con compañeros.

### Requerimiento 9: Navegación Actualizada

**User Story:** Como Trabajador, quiero que la navegación de mi3 incluya las nuevas secciones de Préstamos y Reemplazos, para que pueda acceder fácilmente a toda mi información laboral.

#### Criterios de Aceptación

1. THE Sistema_Mi3 SHALL agregar "Préstamos" como item en la navegación principal (bottom nav) del trabajador, reemplazando "Crédito" que pasará a la navegación secundaria (sheet "Más").
2. THE Sistema_Mi3 SHALL agregar "Reemplazos" como item en la navegación secundaria (sheet "Más") del trabajador.
3. WHEN un Trabajador tiene un préstamo con estado 'pendiente' (esperando aprobación), THE Sistema_Mi3 SHALL mostrar un indicador visual (badge) en el ícono de Préstamos de la navegación.
4. THE navegación principal (bottom nav) SHALL mostrar: Inicio, Turnos, Sueldo, Préstamos. La navegación secundaria SHALL mostrar: Perfil, Crédito, Reemplazos, Asistencia, Cambios, Notificaciones.

### Requerimiento 10: API Backend para Préstamos

**User Story:** Como Administrador técnico, quiero que el backend Laravel exponga endpoints REST para el sistema de préstamos, para que el frontend pueda gestionar solicitudes y aprobaciones.

#### Criterios de Aceptación

1. THE API_Mi3 SHALL exponer `GET /api/v1/worker/loans` que retorne los préstamos del Trabajador autenticado, ordenados por `created_at` descendente.
2. THE API_Mi3 SHALL exponer `POST /api/v1/worker/loans` que cree una solicitud de préstamo validando: monto > 0, monto <= sueldo base del trabajador, cuotas entre 1 y 3, y que no exista un préstamo activo (estado 'aprobado' con cuotas pendientes).
3. THE API_Mi3 SHALL exponer `GET /api/v1/admin/loans` que retorne todos los préstamos con datos del trabajador, ordenados por estado y fecha.
4. THE API_Mi3 SHALL exponer `POST /api/v1/admin/loans/{id}/approve` que apruebe un préstamo validando: estado actual es 'pendiente', monto aprobado > 0, y creando el ajuste positivo en Tabla_Ajustes_Sueldo.
5. THE API_Mi3 SHALL exponer `POST /api/v1/admin/loans/{id}/reject` que rechace un préstamo validando que el estado actual es 'pendiente'.
6. THE API_Mi3 SHALL exponer `GET /api/v1/worker/dashboard-summary` que retorne en una sola respuesta: total liquidación del mes, préstamo activo (si existe), suma de descuentos del mes por categoría, y resumen de reemplazos del mes.

### Requerimiento 11: Push Notifications Nativas

**User Story:** Como Trabajador, quiero recibir notificaciones push en mi celular cuando algo importante pasa (préstamo aprobado, turno cambiado, reemplazo asignado), para que no tenga que abrir la app constantemente para enterarme.

#### Criterios de Aceptación

1. THE Sistema_Mi3 SHALL implementar un Service Worker (`sw.js`) que escuche eventos `push` y muestre notificaciones nativas del sistema operativo con título, cuerpo, ícono y vibración.
2. WHEN un Trabajador accede a mi3 por primera vez, THE Sistema_Mi3 SHALL solicitar permiso para enviar notificaciones push y, si el usuario acepta, suscribir el dispositivo vía Web Push API (VAPID).
3. THE Sistema_Mi3 SHALL crear la tabla `push_subscriptions_mi3` con columnas: `id`, `personal_id` (FK → personal), `subscription` (JSON con endpoint, keys), `is_active` (default 1), `created_at`, `updated_at`.
4. THE API_Mi3 SHALL exponer `POST /api/v1/worker/push/subscribe` que guarde la suscripción push del trabajador autenticado.
5. THE backend de mi3 SHALL instalar la librería `minishlink/web-push` y configurar VAPID keys como variables de entorno (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`).
6. THE Sistema_Mi3 SHALL enviar push notification al Trabajador cuando: (a) su préstamo es aprobado o rechazado, (b) un reemplazo es asignado a su turno, (c) una solicitud de cambio de turno es aprobada o rechazada, (d) se genera su liquidación mensual.
7. THE Sistema_Mi3 SHALL enviar push notification al Administrador cuando: (a) un trabajador solicita un préstamo, (b) un trabajador solicita un cambio de turno.
8. WHEN una suscripción push falla al enviar (endpoint expirado), THE Sistema_Mi3 SHALL marcar la suscripción como `is_active = 0` y no intentar enviar nuevamente.
9. THE push notifications SHALL funcionar en Android (Chrome/Firefox/Samsung con PWA instalada) y iOS (Safari 16.4+ con PWA instalada).
10. THE Sistema_Mi3 SHALL mostrar un badge con contador de notificaciones no leídas en el ícono de la app instalada (PWA badge API).

### Requerimiento 12: Gestión de Notificaciones In-App

**User Story:** Como Trabajador, quiero ver mis notificaciones dentro de la app con un indicador de no leídas, para que pueda revisar el historial de eventos importantes.

#### Criterios de Aceptación

1. WHEN el Sistema_Mi3 crea una notificación en `notificaciones_mi3`, THE Sistema_Mi3 SHALL también intentar enviar una push notification al dispositivo del trabajador (si tiene suscripción activa).
2. THE MobileHeader SHALL mostrar un badge con el conteo de notificaciones no leídas que se actualice al navegar entre páginas.
3. WHEN un Trabajador accede a `/dashboard/notificaciones`, THE Sistema_Mi3 SHALL marcar las notificaciones como leídas y actualizar el badge a 0.
