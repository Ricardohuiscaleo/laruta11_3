# Documento de Requerimientos — mi3 RRHH

## Introducción

mi3 (mi.laruta11.cl) es una aplicación de autoservicio de Recursos Humanos para los trabajadores de La Ruta 11. Permite a los ~10 trabajadores (cajeros, plancheros, riders, seguridad, admin) consultar su información laboral, turnos, liquidaciones y crédito R11 desde sus celulares. Para el administrador, reemplaza y mejora el módulo PersonalApp actual de caja3 con automatización de procesos como el descuento de crédito R11 en nómina. La arquitectura es de dos aplicaciones: un frontend Next.js (App Router) y un backend Laravel exclusivo con API REST. Ambas se conectan a la misma base de datos MySQL compartida con app3/caja3.

## Glosario

- **Sistema_Mi3**: Aplicación web mi3 compuesta por frontend (mi.laruta11.cl, Next.js puerto 3000) y backend (api-mi3.laruta11.cl, Laravel puerto 8080)
- **API_Mi3**: Backend Laravel exclusivo para mi3 con API REST, autenticación Sanctum, Eloquent ORM y scheduler para cron jobs
- **Trabajador**: Persona registrada en la tabla `personal` con `activo = 1`, vinculada a `usuarios` vía `user_id`
- **Administrador**: Usuario con rol 'administrador' o 'dueño' en la tabla `personal` que tiene acceso a funciones de gestión
- **Tabla_Personal**: Tabla `personal` de la base de datos MySQL compartida
- **Tabla_Usuarios**: Tabla `usuarios` de la base de datos MySQL compartida
- **Tabla_Turnos**: Tabla `turnos` de la base de datos MySQL compartida
- **Tabla_Ajustes_Sueldo**: Tabla `ajustes_sueldo` de la base de datos MySQL compartida
- **Tabla_Pagos_Nomina**: Tabla `pagos_nomina` de la base de datos MySQL compartida
- **Tabla_Presupuesto_Nomina**: Tabla `presupuesto_nomina` de la base de datos MySQL compartida
- **Tabla_R11_Transactions**: Tabla `r11_credit_transactions` para transacciones de crédito R11
- **Crédito_R11**: Sistema de crédito para trabajadores con campos `es_credito_r11`, `credito_r11_usado`, `limite_credito_r11` en Tabla_Usuarios
- **Liquidación**: Cálculo mensual del sueldo de un Trabajador incluyendo sueldo base, reemplazos, ajustes y descuentos
- **Centro_Costo**: Agrupación de nómina: 'ruta11' (cajeros, plancheros, admin) o 'seguridad' (guardias)
- **Turno_4x4**: Sistema de turnos rotativos de 4 días trabajando / 4 días libres, generado dinámicamente
- **Reemplazo**: Turno donde un Trabajador cubre a otro, con monto de reemplazo asociado
- **Ajuste**: Modificación al sueldo mensual (adelanto, multa, corrección, bono, descuento_credito_r11)
- **Sesión_Trabajador**: Sesión autenticada de un Trabajador vía `session_token` en Tabla_Usuarios, vinculado a Tabla_Personal por `user_id`

## Requerimientos

### Requerimiento 1: Autenticación y Control de Acceso

**User Story:** Como Trabajador, quiero iniciar sesión en mi3 con mi cuenta de La Ruta 11, para que pueda acceder a mi información laboral de forma segura.

#### Criterios de Aceptación

1. WHEN un usuario accede a mi.laruta11.cl, THE Sistema_Mi3 SHALL verificar si existe un `session_token` válido en Tabla_Usuarios y redirigir al dashboard si la sesión es válida.
2. WHEN un usuario no autenticado accede a mi.laruta11.cl, THE Sistema_Mi3 SHALL mostrar una pantalla de login con opciones de Google OAuth y email/contraseña (mismas opciones que app3).
3. WHEN un usuario se autentica exitosamente, THE Sistema_Mi3 SHALL verificar que el `user_id` del usuario exista en Tabla_Personal con `activo = 1`, rechazando el acceso con mensaje descriptivo si no está registrado como trabajador.
4. WHEN un Trabajador autenticado accede a mi3, THE Sistema_Mi3 SHALL determinar su rol consultando el campo `rol` de Tabla_Personal y asignar permisos de Administrador si el rol incluye 'administrador' o 'dueño'.
5. THE Sistema_Mi3 SHALL restringir CORS a los dominios `mi.laruta11.cl`, `app.laruta11.cl` y `caja.laruta11.cl`.
6. EVERY endpoint de mi3/api/ SHALL validar el `session_token` del usuario autenticado antes de procesar, retornando HTTP 401 si la sesión es inválida o el usuario no está en Tabla_Personal.

### Requerimiento 2: Perfil del Trabajador

**User Story:** Como Trabajador, quiero ver mi perfil con mis datos laborales, para que pueda verificar que mi información está correcta.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a su perfil, THE Sistema_Mi3 SHALL mostrar nombre, email, teléfono, RUT, rol(es), foto de perfil y fecha de registro desde Tabla_Personal y Tabla_Usuarios.
2. WHEN un Trabajador accede a su perfil, THE Sistema_Mi3 SHALL mostrar su sueldo base correspondiente a cada rol activo (sueldo_base_cajero, sueldo_base_planchero, sueldo_base_seguridad, sueldo_base_admin).
3. WHEN un Trabajador tiene `es_credito_r11 = 1` en Tabla_Usuarios, THE Sistema_Mi3 SHALL mostrar una sección de Crédito R11 con límite, crédito usado, crédito disponible y estado (aprobado/bloqueado).
4. IF un Trabajador intenta acceder al perfil de otro Trabajador, THEN THE Sistema_Mi3 SHALL rechazar la solicitud con error de autorización.

### Requerimiento 3: Visualización de Turnos y Calendario

**User Story:** Como Trabajador, quiero ver mi calendario de turnos del mes, para que sepa qué días me toca trabajar y quién me reemplaza o a quién reemplazo.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a su calendario de turnos, THE Sistema_Mi3 SHALL mostrar un calendario mensual con los días que le corresponde trabajar, incluyendo turnos normales y turnos 4x4 generados dinámicamente.
2. WHEN un Trabajador tiene turnos de tipo 'reemplazo' o 'reemplazo_seguridad' en un día, THE Sistema_Mi3 SHALL indicar visualmente quién lo reemplaza o a quién está reemplazando, junto con el monto del reemplazo.
3. WHEN un Trabajador consulta turnos de un mes específico, THE Sistema_Mi3 SHALL generar dinámicamente los turnos 4x4 usando la misma lógica de ciclos base que `caja3/api/personal/get_turnos.php` (base Camila/Neit: 2026-02-01, base Gabriel/Andrés: 2026-02-03, base Ricardo/Claudio: 2026-02-11).
4. THE Sistema_Mi3 SHALL permitir al Trabajador navegar entre meses anteriores y futuros en el calendario.
5. WHEN un Trabajador tiene roles en ambos centros de costo (ruta11 y seguridad), THE Sistema_Mi3 SHALL mostrar ambos calendarios diferenciados visualmente.

### Requerimiento 4: Visualización de Liquidación

**User Story:** Como Trabajador, quiero ver mi liquidación mensual detallada, para que entienda cómo se calcula mi sueldo y qué descuentos se aplican.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a su liquidación del mes actual, THE Sistema_Mi3 SHALL mostrar el desglose por Centro_Costo: sueldo base, días trabajados, reemplazos realizados (con monto ganado), reemplazos recibidos (con monto descontado) y ajustes (adelantos, multas, correcciones, bonos).
2. WHEN un Trabajador tiene ajustes en Tabla_Ajustes_Sueldo para el mes consultado, THE Sistema_Mi3 SHALL listar cada ajuste con concepto, categoría, monto (positivo o negativo) y notas.
3. WHEN un Trabajador tiene un descuento de tipo 'descuento_credito_r11' en sus ajustes, THE Sistema_Mi3 SHALL mostrar el descuento con etiqueta "Descuento Crédito R11" y el monto correspondiente.
4. THE Sistema_Mi3 SHALL calcular el total de la liquidación usando la misma lógica que la función `getLiquidacion()` de PersonalApp.jsx: sueldo base + reemplazos realizados (pagados por empresa) - reemplazos recibidos + total de ajustes.
5. THE Sistema_Mi3 SHALL permitir al Trabajador consultar liquidaciones de meses anteriores navegando por mes y año.
6. WHEN un Trabajador consulta su liquidación, THE Sistema_Mi3 SHALL mostrar el total general sumando los totales de todos los centros de costo aplicables.

### Requerimiento 5: Estado de Crédito R11 del Trabajador

**User Story:** Como Trabajador con crédito R11, quiero ver el estado de mi crédito desde mi3, para que sepa cuánto he usado, cuánto me queda disponible y el historial de transacciones.

#### Criterios de Aceptación

1. WHEN un Trabajador con `es_credito_r11 = 1` accede a la sección de crédito, THE Sistema_Mi3 SHALL mostrar: límite de crédito, crédito usado, crédito disponible (límite - usado), relación R11 y fecha de aprobación.
2. WHEN un Trabajador accede a su historial de crédito R11, THE Sistema_Mi3 SHALL listar las transacciones de Tabla_R11_Transactions ordenadas por fecha descendente, mostrando tipo (compra/pago/reintegro), monto, descripción y fecha.
3. WHEN un Trabajador tiene `credito_r11_bloqueado = 1`, THE Sistema_Mi3 SHALL mostrar un banner de alerta indicando que el crédito está bloqueado por falta de pago.
4. WHEN un Trabajador sin `es_credito_r11 = 1` accede a la sección de crédito, THE Sistema_Mi3 SHALL mostrar un mensaje indicando que no tiene crédito R11 activo.

### Requerimiento 6: Solicitud de Cambio de Turno

**User Story:** Como Trabajador, quiero solicitar un cambio de turno con otro compañero, para que pueda coordinar intercambios cuando necesite un día libre.

#### Criterios de Aceptación

1. WHEN un Trabajador selecciona un día de su calendario donde tiene turno asignado, THE Sistema_Mi3 SHALL mostrar la opción "Solicitar cambio" con un selector de compañeros disponibles (trabajadores activos del mismo Centro_Costo).
2. WHEN un Trabajador envía una solicitud de cambio de turno, THE Sistema_Mi3 SHALL crear un registro en una nueva tabla `solicitudes_cambio_turno` con estado 'pendiente', incluyendo solicitante, compañero propuesto, fecha del turno y motivo.
3. WHEN una solicitud de cambio de turno es creada, THE Sistema_Mi3 SHALL enviar una notificación al compañero propuesto y al Administrador.
4. WHEN un Administrador aprueba una solicitud de cambio, THE Sistema_Mi3 SHALL crear los turnos de reemplazo correspondientes en Tabla_Turnos y actualizar el estado de la solicitud a 'aprobada'.
5. WHEN un Administrador rechaza una solicitud de cambio, THE Sistema_Mi3 SHALL actualizar el estado de la solicitud a 'rechazada' y notificar al Trabajador solicitante.
6. THE Sistema_Mi3 SHALL mostrar al Trabajador el historial de sus solicitudes de cambio con el estado actual de cada una (pendiente, aprobada, rechazada).

### Requerimiento 7: Notificaciones para Trabajadores

**User Story:** Como Trabajador, quiero recibir notificaciones sobre mis turnos, pagos y crédito, para que esté informado de cambios importantes sin tener que revisar la app constantemente.

#### Criterios de Aceptación

1. WHEN se aprueba o rechaza una solicitud de cambio de turno, THE Sistema_Mi3 SHALL crear una notificación visible en la app para los trabajadores involucrados.
2. WHEN se envía la liquidación mensual por email, THE Sistema_Mi3 SHALL crear una notificación en la app indicando que la liquidación del mes está disponible.
3. WHEN el crédito R11 de un Trabajador es bloqueado o desbloqueado, THE Sistema_Mi3 SHALL crear una notificación en la app con el detalle del cambio de estado.
4. WHEN se crea un ajuste de sueldo para un Trabajador (adelanto, multa, bono, descuento), THE Sistema_Mi3 SHALL crear una notificación en la app con el concepto y monto del ajuste.
5. THE Sistema_Mi3 SHALL mostrar un indicador de notificaciones no leídas en la navegación principal y permitir al Trabajador marcar notificaciones como leídas.

### Requerimiento 8: Panel de Administración — Gestión de Personal

**User Story:** Como Administrador, quiero gestionar los datos de los trabajadores desde mi3, para que pueda agregar, editar y desactivar personal sin depender de caja3.

#### Criterios de Aceptación

1. WHEN un Administrador accede a la gestión de personal, THE Sistema_Mi3 SHALL listar todos los registros de Tabla_Personal mostrando nombre, rol(es), sueldos base, estado (activo/inactivo) y vinculación con Tabla_Usuarios.
2. WHEN un Administrador agrega un nuevo trabajador, THE Sistema_Mi3 SHALL crear un registro en Tabla_Personal con nombre, rol(es), sueldos base por rol y estado activo, vía `POST /api/v1/admin/personal`.
3. WHEN un Administrador edita un trabajador, THE Sistema_Mi3 SHALL actualizar los campos modificados en Tabla_Personal vía `PUT /api/v1/admin/personal/{id}`.
4. WHEN un Administrador desactiva un trabajador, THE Sistema_Mi3 SHALL actualizar `activo = 0` en Tabla_Personal sin eliminar el registro.
5. WHEN un Administrador vincula un trabajador con una cuenta de usuario, THE Sistema_Mi3 SHALL actualizar el campo `user_id` en Tabla_Personal con el ID correspondiente de Tabla_Usuarios.

### Requerimiento 9: Panel de Administración — Gestión de Turnos

**User Story:** Como Administrador, quiero gestionar los turnos de los trabajadores desde mi3, para que pueda asignar turnos, registrar reemplazos y ver el calendario completo del equipo.

#### Criterios de Aceptación

1. WHEN un Administrador accede al calendario de turnos, THE Sistema_Mi3 SHALL mostrar un calendario mensual con todos los trabajadores, diferenciando visualmente turnos normales, turnos de seguridad y reemplazos, usando la misma lógica de generación dinámica 4x4 que `get_turnos.php`.
2. WHEN un Administrador selecciona un día en el calendario, THE Sistema_Mi3 SHALL permitir asignar un turno a un trabajador, registrar un reemplazo (seleccionando titular, reemplazante, monto y quién paga) o eliminar un turno existente.
3. WHEN un Administrador guarda un turno o reemplazo, THE Sistema_Mi3 SHALL persistir los cambios en Tabla_Turnos vía `POST /api/v1/admin/turnos` y `DELETE /api/v1/admin/turnos/{id}`.
4. WHEN un Administrador asigna un rango de fechas para un turno, THE Sistema_Mi3 SHALL crear un turno por cada día del rango, igual que la funcionalidad actual de PersonalApp.jsx.
5. WHEN existen solicitudes de cambio de turno pendientes, THE Sistema_Mi3 SHALL mostrar un indicador en el calendario y permitir al Administrador aprobar o rechazar desde la vista de turnos.

### Requerimiento 10: Panel de Administración — Nómina y Liquidaciones

**User Story:** Como Administrador, quiero gestionar la nómina mensual desde mi3, para que pueda ver el resumen de sueldos, registrar pagos y enviar liquidaciones por email.

#### Criterios de Aceptación

1. WHEN un Administrador accede a la nómina del mes, THE Sistema_Mi3 SHALL mostrar el resumen por Centro_Costo (ruta11 y seguridad) con: presupuesto, total de sueldos calculados, total pagado y diferencia, usando los datos de Tabla_Pagos_Nomina y Tabla_Presupuesto_Nomina.
2. WHEN un Administrador accede a la nómina, THE Sistema_Mi3 SHALL mostrar una tarjeta por cada Trabajador activo con: nombre, rol, sueldo base, días trabajados, reemplazos, ajustes y total de liquidación calculado.
3. WHEN un Administrador registra un pago de nómina, THE Sistema_Mi3 SHALL persistir el pago vía `POST /api/v1/admin/nomina/pagos` con personal_id, nombre, monto, centro de costo y notas.
4. WHEN un Administrador modifica el presupuesto de nómina, THE Sistema_Mi3 SHALL actualizar el monto por Centro_Costo vía `PUT /api/v1/admin/nomina/presupuesto`.
5. WHEN un Administrador envía una liquidación por email a un Trabajador, THE Sistema_Mi3 SHALL generar el email desde el backend Laravel usando el mismo template HTML que PersonalApp.jsx y enviarlo vía Gmail API.
6. WHEN un Administrador envía liquidaciones masivas, THE Sistema_Mi3 SHALL encolar los envíos usando Laravel Queue para procesarlos secuencialmente sin bloquear la UI, reportando progreso vía polling o SSE.

### Requerimiento 11: Panel de Administración — Ajustes de Sueldo

**User Story:** Como Administrador, quiero gestionar los ajustes de sueldo de los trabajadores desde mi3, para que pueda registrar adelantos, multas, bonos y descuentos de crédito R11.

#### Criterios de Aceptación

1. WHEN un Administrador agrega un ajuste de sueldo, THE Sistema_Mi3 SHALL crear el registro en Tabla_Ajustes_Sueldo vía `POST /api/v1/admin/ajustes` con personal_id, mes, monto (positivo o negativo según signo), concepto, categoría y notas.
2. WHEN un Administrador elimina un ajuste de sueldo, THE Sistema_Mi3 SHALL eliminar el registro de Tabla_Ajustes_Sueldo vía `DELETE /api/v1/admin/ajustes/{id}`.
3. THE Sistema_Mi3 SHALL exponer las categorías de ajuste disponibles vía `GET /api/v1/admin/ajustes/categorias`.
4. WHEN un Administrador lista los ajustes del mes, THE Sistema_Mi3 SHALL mostrar todos los ajustes agrupados por Trabajador con concepto, categoría, monto y notas vía `GET /api/v1/admin/ajustes?mes={YYYY-MM}`.

### Requerimiento 12: Automatización del Descuento de Crédito R11 en Nómina

**User Story:** Como Administrador, quiero que el descuento del crédito R11 de cada trabajador se aplique automáticamente en su liquidación el día 1 de cada mes, para que no tenga que crear ajustes manuales por cada deudor.

#### Criterios de Aceptación

1. WHEN llega el día 1 del mes, THE Sistema_Mi3 SHALL ejecutar un proceso que consulte todos los Trabajadores con `es_credito_r11 = 1` y `credito_r11_usado > 0` en Tabla_Usuarios, vinculados a Tabla_Personal por `user_id`.
2. WHEN un Trabajador tiene deuda de crédito R11, THE Sistema_Mi3 SHALL crear automáticamente un ajuste en Tabla_Ajustes_Sueldo con categoría 'descuento_credito_r11', monto negativo igual al `credito_r11_usado`, concepto "Descuento Crédito R11 - [mes]" y el personal_id correspondiente.
3. WHEN el ajuste de descuento R11 es creado, THE Sistema_Mi3 SHALL procesar el pago del crédito R11 directamente desde el backend Laravel: decrementar `credito_r11_usado` en Tabla_Usuarios, insertar transacción 'refund' en Tabla_R11_Transactions y actualizar `fecha_ultimo_pago_r11`. Todo dentro de una transacción de base de datos.
4. WHEN el proceso de descuento R11 finaliza, THE Sistema_Mi3 SHALL enviar un resumen al Administrador por email con la lista de trabajadores procesados, montos descontados y estado final del crédito de cada uno.
5. IF un Trabajador no tiene registro en Tabla_Personal vinculado por `user_id`, THEN THE Sistema_Mi3 SHALL omitir el descuento automático para ese usuario y registrar una advertencia en el resumen del Administrador.

### Requerimiento 13: Registro de Asistencia

**User Story:** Como Trabajador, quiero ver mi registro de asistencia mensual, para que pueda verificar los días que trabajé y detectar discrepancias.

#### Criterios de Aceptación

1. WHEN un Trabajador accede a su registro de asistencia, THE Sistema_Mi3 SHALL mostrar un resumen mensual con: total de días trabajados (turnos normales + reemplazos realizados), días de descanso, días donde fue reemplazado y total de días del mes.
2. WHEN un Trabajador consulta su asistencia, THE Sistema_Mi3 SHALL calcular los días trabajados usando la misma lógica que `getLiquidacion()`: turnos normales propios + turnos donde fue reemplazante, separados por Centro_Costo.
3. THE Sistema_Mi3 SHALL permitir al Trabajador navegar entre meses para consultar asistencia histórica.

### Requerimiento 14: Infraestructura y Despliegue

**User Story:** Como Administrador técnico, quiero que mi3 tenga un backend Laravel exclusivo y un frontend Next.js, desplegados en Coolify como dos aplicaciones separadas.

#### Criterios de Aceptación

1. THE Sistema_Mi3 SHALL tener dos aplicaciones separadas dentro del monorepo: `mi3/frontend` (Next.js App Router + React + TailwindCSS + lucide-react) y `mi3/backend` (Laravel 11 con PHP 8.3).
2. THE frontend de mi3 SHALL incluir un Dockerfile que exponga el puerto 3000 y use `next start` en producción. Dominio: `mi.laruta11.cl`.
3. THE backend de mi3 SHALL incluir un Dockerfile que exponga el puerto 8080 y use Apache/Nginx con PHP-FPM. Dominio: `api-mi3.laruta11.cl`.
4. THE backend de mi3 SHALL conectarse a la misma base de datos MySQL compartida usando las variables de entorno APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS, configuradas en el `.env` de Laravel.
5. THE backend de mi3 SHALL usar Laravel Sanctum para autenticación de API (tokens stateless), Eloquent ORM para acceso a datos, y Laravel Scheduler para cron jobs (descuento R11 día 1, recordatorios).
6. THE backend de mi3 SHALL exponer una API REST completa con prefijo `/api/v1/` que cubra: autenticación, perfil, turnos, liquidaciones, crédito R11, solicitudes de cambio, notificaciones, y administración. NO dependerá de las APIs PHP de caja3.
7. THE frontend de mi3 SHALL consumir exclusivamente la API del backend Laravel. NO hará fetch a APIs de caja3 ni app3.
8. THE frontend de mi3 SHALL implementar middleware de Next.js (`middleware.ts`) para control de acceso: rutas `/admin/*` solo para rol 'administrador' o 'dueño', rutas `/dashboard/*` para cualquier Trabajador autenticado.
9. THE Sistema_Mi3 SHALL ser mobile-first, con diseño responsive optimizado para pantallas de celular como interfaz principal de los trabajadores.
10. THE backend de mi3 SHALL usar migraciones de Laravel para crear las tablas nuevas (`solicitudes_cambio_turno`, `notificaciones_mi3`) y para definir los modelos Eloquent de las tablas existentes (`personal`, `usuarios`, `turnos`, `ajustes_sueldo`, `pagos_nomina`, `r11_credit_transactions`) sin modificar su estructura.
11. THE backend de mi3 SHALL estar organizado por dominio con carpetas `Worker/` y `Admin/` en Controllers, Requests y Services, preparado para incorporar módulos futuros (compras, inventario, reportes) sin reestructurar.

#### Estructura de Carpetas — Backend Laravel (`mi3/backend`)

```
mi3/backend/
├── app/
│   ├── Console/Commands/
│   │   ├── R11AutoDeductCommand.php          # Descuento automático R11 día 1
│   │   └── R11ReminderCommand.php            # Recordatorio día 28
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php        # Login, logout, me
│   │   │   ├── Worker/                       # === DASHBOARD TRABAJADOR ===
│   │   │   │   ├── ProfileController.php
│   │   │   │   ├── ShiftController.php
│   │   │   │   ├── PayrollController.php
│   │   │   │   ├── CreditController.php
│   │   │   │   ├── ShiftSwapController.php
│   │   │   │   ├── AttendanceController.php
│   │   │   │   └── NotificationController.php
│   │   │   └── Admin/                        # === DASHBOARD ADMIN ===
│   │   │       ├── PersonalController.php
│   │   │       ├── ShiftController.php
│   │   │       ├── PayrollController.php
│   │   │       ├── AdjustmentController.php
│   │   │       ├── CreditController.php
│   │   │       ├── ShiftSwapController.php
│   │   │       ├── NotificationController.php
│   │   │       ├── PurchaseController.php    # (futuro)
│   │   │       ├── InventoryController.php   # (futuro)
│   │   │       └── ReportController.php      # (futuro)
│   │   ├── Middleware/
│   │   │   ├── EnsureIsWorker.php
│   │   │   └── EnsureIsAdmin.php
│   │   └── Requests/
│   │       ├── Worker/
│   │       │   └── ShiftSwapRequest.php
│   │       └── Admin/
│   │           ├── StorePersonalRequest.php
│   │           ├── StoreShiftRequest.php
│   │           └── StoreAdjustmentRequest.php
│   ├── Models/
│   │   ├── Usuario.php
│   │   ├── Personal.php
│   │   ├── Turno.php
│   │   ├── AjusteSueldo.php
│   │   ├── AjusteCategoria.php
│   │   ├── PagoNomina.php
│   │   ├── PresupuestoNomina.php
│   │   ├── R11CreditTransaction.php
│   │   ├── SolicitudCambioTurno.php          # nueva
│   │   ├── NotificacionMi3.php               # nueva
│   │   ├── TuuOrder.php
│   │   └── EmailLog.php
│   └── Services/
│       ├── Auth/AuthService.php
│       ├── Payroll/
│       │   ├── LiquidacionService.php        # Replica getLiquidacion()
│       │   └── NominaService.php
│       ├── Shift/
│       │   ├── ShiftService.php              # Generación 4x4
│       │   └── ShiftSwapService.php
│       ├── Credit/R11CreditService.php
│       ├── Notification/NotificationService.php
│       └── Email/GmailService.php
├── database/migrations/
│   ├── 2026_04_10_000001_create_solicitudes_cambio_turno.php
│   ├── 2026_04_10_000002_create_notificaciones_mi3.php
│   └── 2026_04_10_000003_add_descuento_credito_r11_categoria.php
├── routes/
│   ├── api.php                               # Rutas /api/v1/
│   └── console.php                           # Scheduler
├── Dockerfile
└── .env.example
```

#### Estructura de Carpetas — Frontend Next.js (`mi3/frontend`)

```
mi3/frontend/
├── app/
│   ├── layout.tsx
│   ├── page.tsx                              # Redirect a login o dashboard
│   ├── login/page.tsx
│   ├── dashboard/                            # === TRABAJADOR ===
│   │   ├── layout.tsx                        # Sidebar trabajador
│   │   ├── page.tsx                          # Home: turnos hoy, crédito, alertas
│   │   ├── perfil/page.tsx
│   │   ├── turnos/page.tsx
│   │   ├── liquidacion/page.tsx
│   │   ├── credito/page.tsx
│   │   ├── asistencia/page.tsx
│   │   ├── cambios/page.tsx
│   │   └── notificaciones/page.tsx
│   └── admin/                                # === ADMIN ===
│       ├── layout.tsx                        # Sidebar admin (expandido)
│       ├── page.tsx                          # Home: resumen nómina, alertas
│       ├── personal/page.tsx
│       ├── turnos/page.tsx
│       ├── nomina/page.tsx
│       ├── ajustes/page.tsx
│       ├── creditos/page.tsx
│       ├── cambios/page.tsx
│       ├── compras/page.tsx                  # (futuro)
│       ├── inventario/page.tsx               # (futuro)
│       └── reportes/page.tsx                 # (futuro)
├── components/
│   ├── ui/                                   # Button, Card, Input, etc
│   ├── layouts/
│   │   ├── WorkerSidebar.tsx
│   │   └── AdminSidebar.tsx
│   ├── worker/                               # Componentes trabajador
│   └── admin/                                # Componentes admin
├── lib/
│   ├── api.ts                                # Fetch wrapper con auth
│   ├── auth.ts
│   └── utils.ts                              # Formato CLP, fechas
├── hooks/
│   ├── useAuth.ts
│   └── useApi.ts
├── types/index.ts
├── middleware.ts                              # Control acceso por rol
├── Dockerfile
└── package.json
```

#### Rutas API (`routes/api.php`)

```
/api/v1/
├── auth/
│   ├── POST   login
│   ├── POST   logout
│   └── GET    me
├── worker/                                   # middleware: EnsureIsWorker
│   ├── GET    profile
│   ├── GET    shifts?mes=YYYY-MM
│   ├── GET    payroll?mes=YYYY-MM
│   ├── GET    credit
│   ├── GET    credit/transactions
│   ├── GET    attendance?mes=YYYY-MM
│   ├── GET    shift-swaps
│   ├── POST   shift-swaps
│   ├── GET    notifications
│   └── PATCH  notifications/{id}/read
└── admin/                                    # middleware: EnsureIsAdmin
    ├── personal/     GET, POST, PUT/{id}, PATCH/{id}/toggle
    ├── shifts/       GET, POST, DELETE/{id}
    ├── payroll/      GET, POST payments, PUT budget, POST send-liquidacion
    ├── adjustments/  GET, GET categories, POST, DELETE/{id}
    ├── credits/      GET, POST/{id}/approve, POST/{id}/reject, POST/{id}/manual-payment
    ├── shift-swaps/  GET, POST/{id}/approve, POST/{id}/reject
    ├── purchases/    (futuro)
    ├── inventory/    (futuro)
    └── reports/      (futuro)
```

### Requerimiento 15: Modelo de Datos — Nuevas Tablas

**User Story:** Como Administrador técnico, quiero que las nuevas tablas necesarias para mi3 estén definidas, para que el sistema pueda almacenar solicitudes de cambio de turno y notificaciones.

#### Criterios de Aceptación

1. THE Sistema_Mi3 SHALL crear la tabla `solicitudes_cambio_turno` con columnas: `id` (PK AUTO_INCREMENT), `solicitante_id` (INT NOT NULL FK → personal), `compañero_id` (INT NOT NULL FK → personal), `fecha_turno` (DATE NOT NULL), `motivo` (VARCHAR 255), `estado` (ENUM 'pendiente','aprobada','rechazada' DEFAULT 'pendiente'), `aprobado_por` (INT NULL FK → personal), `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP), `updated_at` (TIMESTAMP ON UPDATE CURRENT_TIMESTAMP).
2. THE Sistema_Mi3 SHALL crear la tabla `notificaciones_mi3` con columnas: `id` (PK AUTO_INCREMENT), `personal_id` (INT NOT NULL FK → personal), `tipo` (ENUM 'turno','liquidacion','credito','ajuste','sistema'), `titulo` (VARCHAR 255 NOT NULL), `mensaje` (TEXT), `leida` (TINYINT DEFAULT 0), `referencia_id` (INT NULL), `referencia_tipo` (VARCHAR 50 NULL), `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP).
3. THE Sistema_Mi3 SHALL agregar la categoría 'descuento_credito_r11' a la tabla `ajustes_categorias` si no existe, con nombre "Descuento Crédito R11", slug "descuento_credito_r11" e ícono apropiado.
