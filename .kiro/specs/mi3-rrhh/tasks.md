# Plan de Implementación: mi3 RRHH

## Resumen

Implementación incremental del sistema mi3 RRHH: primero scaffolding de ambas apps, luego backend (modelos → servicios → controllers → rutas), después frontend (páginas trabajador → admin), cron jobs, y finalmente tests. Backend en PHP/Laravel 11, frontend en TypeScript/Next.js.

## Tareas

- [ ] 1. Scaffolding del backend Laravel
  - [ ] 1.1 Crear proyecto Laravel 11 en `mi3/backend`
    - Ejecutar `laravel new backend` dentro de `mi3/`
    - Instalar Laravel Sanctum (`composer require laravel/sanctum`)
    - Configurar `.env.example` con variables APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS
    - Configurar `config/cors.php` con dominios permitidos: `mi.laruta11.cl`, `app.laruta11.cl`, `caja.laruta11.cl`
    - Configurar Sanctum para tokens stateless en `config/sanctum.php`
    - _Requerimientos: 14.1, 14.4, 14.5, 1.5_

  - [ ] 1.2 Crear Dockerfile del backend
    - Dockerfile con PHP 8.3 + Apache/PHP-FPM, puerto 8080
    - Incluir extensiones: pdo_mysql, mbstring, openssl, tokenizer
    - Configurar `ENTRYPOINT` con `php artisan serve` o Apache vhost
    - _Requerimientos: 14.3_

  - [ ] 1.3 Crear estructura de carpetas por dominio
    - Crear directorios: `Controllers/Auth/`, `Controllers/Worker/`, `Controllers/Admin/`
    - Crear directorios: `Requests/Worker/`, `Requests/Admin/`
    - Crear directorios: `Services/Auth/`, `Services/Payroll/`, `Services/Shift/`, `Services/Credit/`, `Services/Notification/`, `Services/Email/`
    - Crear directorios: `Console/Commands/`
    - _Requerimientos: 14.11_

- [ ] 2. Scaffolding del frontend Next.js
  - [ ] 2.1 Crear proyecto Next.js en `mi3/frontend`
    - Ejecutar `npx create-next-app@latest frontend` dentro de `mi3/` con App Router, TypeScript, TailwindCSS
    - Instalar `lucide-react`
    - Crear estructura de carpetas: `app/login/`, `app/dashboard/`, `app/admin/`, `components/ui/`, `components/layouts/`, `components/worker/`, `components/admin/`, `lib/`, `hooks/`, `types/`
    - _Requerimientos: 14.1, 14.7_

  - [ ] 2.2 Crear Dockerfile del frontend
    - Dockerfile multi-stage: build con `next build`, producción con `next start` en puerto 3000
    - _Requerimientos: 14.2_

  - [ ] 2.3 Crear archivos base del frontend
    - `types/index.ts` — interfaces TypeScript para User, Personal, Turno, Liquidacion, CreditoR11, Notificacion, SolicitudCambio
    - `lib/api.ts` — fetch wrapper con manejo de token Bearer, base URL configurable
    - `lib/utils.ts` — helpers para formato CLP (`$450.000`), formato de fechas en español
    - `hooks/useAuth.ts` — hook de autenticación (login, logout, user state)
    - `hooks/useApi.ts` — hook genérico para llamadas API con loading/error
    - _Requerimientos: 14.7, 14.1_

- [ ] 3. Checkpoint — Verificar scaffolding
  - Asegurar que ambos proyectos compilan sin errores. Preguntar al usuario si hay dudas.


- [ ] 4. Modelos Eloquent — Tablas existentes
  - [ ] 4.1 Crear modelos para tablas existentes
    - `Models/Usuario.php` — tabla `usuarios`, relaciones: personal (hasOne), r11Transactions (hasMany). Casts para campos de crédito R11
    - `Models/Personal.php` — tabla `personal`, relaciones: usuario (belongsTo), turnos (hasMany), ajustes (hasMany), notificaciones (hasMany). Helpers: getRolesArray(), isAdmin(), hasRole()
    - `Models/Turno.php` — tabla `turnos`, relaciones: titular (belongsTo Personal), reemplazante (belongsTo Personal). Cast fecha a date, monto a float
    - `Models/AjusteSueldo.php` — tabla `ajustes_sueldo`, relaciones: personal (belongsTo), categoria (belongsTo AjusteCategoria)
    - `Models/AjusteCategoria.php` — tabla `ajustes_categorias`
    - `Models/R11CreditTransaction.php` — tabla `r11_credit_transactions`, relación: usuario (belongsTo)
    - `Models/PagoNomina.php` — tabla `pagos_nomina`
    - `Models/PresupuestoNomina.php` — tabla `presupuesto_nomina`
    - `Models/TuuOrder.php` — tabla `tuu_orders`
    - `Models/EmailLog.php` — tabla `email_logs`
    - Todos con `$timestamps = false` y `$table` explícito. Sin migraciones para estas tablas
    - _Requerimientos: 14.10, 14.5_

  - [ ] 4.2 Crear modelos y migraciones para tablas nuevas
    - `Models/SolicitudCambioTurno.php` — tabla `solicitudes_cambio_turno`, relaciones: solicitante, compañero, aprobadoPor (belongsTo Personal)
    - `Models/NotificacionMi3.php` — tabla `notificaciones_mi3`, relación: personal (belongsTo)
    - Migración `create_solicitudes_cambio_turno` con columnas según diseño (id, solicitante_id, compañero_id, fecha_turno, motivo, estado ENUM, aprobado_por, timestamps). Índices en solicitante_id, compañero_id, estado
    - Migración `create_notificaciones_mi3` con columnas según diseño (id, personal_id, tipo ENUM, titulo, mensaje, leida, referencia_id, referencia_tipo, created_at). Índices en personal_id y (personal_id, leida)
    - Migración `add_descuento_credito_r11_categoria` — INSERT en ajustes_categorias si no existe
    - _Requerimientos: 15.1, 15.2, 15.3_

- [ ] 5. Middleware y Autenticación
  - [ ] 5.1 Crear middleware de autenticación
    - `Middleware/EnsureIsWorker.php` — verifica token Sanctum, busca personal con user_id y activo=1, inyecta `personal` en request. Retorna 401/403 según caso
    - `Middleware/EnsureIsAdmin.php` — verifica que personal.isAdmin() sea true. Retorna 403 si no
    - Registrar ambos middleware en `bootstrap/app.php`
    - _Requerimientos: 1.3, 1.4, 1.6_

  - [ ] 5.2 Crear AuthService y AuthController
    - `Services/Auth/AuthService.php` — login con email/password, login con Google OAuth token, verificación de session_token en usuarios, vinculación con personal
    - `Controllers/Auth/AuthController.php` — endpoints: POST login, POST logout, GET me
    - Registrar rutas en `routes/api.php` bajo prefijo `v1/auth`
    - _Requerimientos: 1.1, 1.2, 1.3, 1.4_

- [ ] 6. Servicios de negocio — Backend
  - [ ] 6.1 Implementar ShiftService (generación 4x4)
    - `Services/Shift/ShiftService.php` — método `getShiftsForMonth(string $mes)`
    - Implementar algoritmo 4x4: para cada ciclo (seguridad, cajeros, plancheros), calcular `((diffDias % 8) + 8) % 8` desde fecha base
    - Ciclos configurados: Seguridad base=2026-02-11 (Ricardo/Claudio), Cajeros base=2026-02-01 (Camila/Neit), Plancheros base=2026-02-03 (Gabriel/Andrés)
    - Combinar turnos dinámicos con turnos de BD (Tabla_Turnos), filtrar duplicados de IDs 1-4 para turnos normales manuales
    - Ordenar resultado por fecha y personal_id
    - _Requerimientos: 3.1, 3.3, 9.1_

  - [ ]* 6.2 Test de propiedad: Generación de turnos 4x4
    - **Propiedad 3: Correctitud de la generación de turnos 4x4**
    - Generar fechas random en rango amplio, verificar que el trabajador asignado sea personaA si `((diff % 8) + 8) % 8 < 4`, personaB en caso contrario
    - 100+ iteraciones con fechas aleatorias
    - **Valida: Requerimientos 3.1, 3.3, 9.1**

  - [ ] 6.3 Implementar LiquidacionService (cálculo de liquidación)
    - `Services/Payroll/LiquidacionService.php` — método `calcular(Personal $persona, string $mes, string $modoContexto = 'all')`
    - Replicar exactamente la lógica de `getLiquidacion()` de PersonalApp.jsx:
      - Filtrar turnos por contexto (seguridad/ruta11/all)
      - Contar días normales, reemplazos realizados, reemplazos recibidos
      - Calcular días trabajados: seguridad = 30 - reemplazados, ruta11 = normales + reemplazos hechos
      - Determinar sueldo base según rol y contexto
      - Incluir ajustes solo una vez (evitar doble conteo entre centros de costo)
      - Total reemplazando: solo pago_por='empresa'
      - Total reemplazados: pago_por='empresa' o 'empresa_adelanto'
      - Total = round(sueldoBase + totalReemplazando - totalReemplazados + totalAjustes)
    - _Requerimientos: 4.1, 4.4, 10.2, 13.1, 13.2_

  - [ ]* 6.4 Test de propiedad: Fórmula de liquidación
    - **Propiedad 4: Correctitud de la fórmula de liquidación**
    - Generar trabajadores con turnos, ajustes y sueldos random. Verificar que total = round(sueldoBase + totalReemplazando - totalReemplazados + totalAjustes)
    - 100+ iteraciones
    - **Valida: Requerimientos 4.1, 4.4, 10.2, 13.1, 13.2**

  - [ ]* 6.5 Test de propiedad: Gran total es suma de centros de costo
    - **Propiedad 5: Gran total es suma de centros de costo**
    - Generar trabajadores con roles duales, verificar que gran_total = total_ruta11 + total_seguridad
    - **Valida: Requerimiento 4.6**

  - [ ] 6.6 Implementar NominaService
    - `Services/Payroll/NominaService.php` — resumen de nómina por mes y centro de costo
    - Calcular liquidación de cada trabajador activo, agrupar por centro de costo
    - Consultar presupuesto y pagos registrados
    - _Requerimientos: 10.1, 10.2_

  - [ ] 6.7 Implementar R11CreditService
    - `Services/Credit/R11CreditService.php` — método `autoDeduct()`
    - Consultar deudores: es_credito_r11=1 AND credito_r11_usado > 0, vinculados a personal activo
    - Para cada deudor en transacción DB: crear ajuste_sueldo (descuento_credito_r11), crear r11_credit_transaction (refund), resetear credito_r11_usado=0, desbloquear si estaba bloqueado
    - Omitir usuarios sin personal vinculado, registrar advertencia
    - Enviar resumen por email al admin
    - _Requerimientos: 12.1, 12.2, 12.3, 12.4, 12.5_

  - [ ]* 6.8 Test de propiedad: Selección de deudores R11
    - **Propiedad 10: Selección de deudores para descuento automático R11**
    - Generar usuarios con estados de crédito variados, verificar que solo se procesan los que cumplen todas las condiciones
    - **Valida: Requerimientos 12.1, 12.5**

  - [ ]* 6.9 Test de propiedad: Round-trip descuento R11
    - **Propiedad 11: Round-trip del descuento automático R11**
    - Verificar que después del descuento: existe ajuste con monto negativo, existe transacción refund, credito_r11_usado = 0
    - **Valida: Requerimientos 12.2, 12.3**

  - [ ] 6.10 Implementar ShiftSwapService
    - `Services/Shift/ShiftSwapService.php` — crear solicitud, aprobar (crear turnos de reemplazo), rechazar
    - Filtrar compañeros disponibles: activo=1, mismo centro de costo, excluir solicitante
    - _Requerimientos: 6.1, 6.2, 6.4, 6.5_

  - [ ] 6.11 Implementar NotificationService
    - `Services/Notification/NotificationService.php` — crear notificación, marcar como leída, contar no leídas
    - Tipos: turno, liquidacion, credito, ajuste, sistema
    - _Requerimientos: 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ] 6.12 Implementar GmailService
    - `Services/Email/GmailService.php` — enviar liquidación por email vía Gmail API
    - Obtener token de tabla gmail_tokens, refresh si expirado
    - Construir mensaje MIME con template HTML (mismo diseño que email_template.php de caja3)
    - Registrar en email_logs
    - Manejar errores: retry 3 veces vía Queue
    - _Requerimientos: 10.5, 10.6_

- [ ] 7. Checkpoint — Verificar servicios de negocio
  - Asegurar que todos los servicios compilan sin errores y los tests de propiedad pasan. Preguntar al usuario si hay dudas.


- [ ] 8. Controllers y Rutas — Worker API
  - [x] 8.1 Crear Form Requests de validación Worker
    - `Requests/Worker/ShiftSwapRequest.php` — validar fecha_turno (date, after:today), compañero_id (exists:personal,id), motivo (nullable, max:255)
    - _Requerimientos: 6.2_

  - [x] 8.2 Implementar controllers Worker
    - `Controllers/Worker/ProfileController.php` — GET profile: datos de personal + usuario + crédito R11
    - `Controllers/Worker/ShiftController.php` — GET shifts: turnos del mes vía ShiftService, filtrados por personal_id
    - `Controllers/Worker/PayrollController.php` — GET payroll: liquidación del mes vía LiquidacionService
    - `Controllers/Worker/CreditController.php` — GET credit: estado crédito R11. GET credit/transactions: historial
    - `Controllers/Worker/AttendanceController.php` — GET attendance: resumen asistencia mensual (días normales, reemplazos, descansos)
    - `Controllers/Worker/ShiftSwapController.php` — GET shift-swaps: historial solicitudes. POST shift-swaps: crear solicitud vía ShiftSwapService
    - `Controllers/Worker/NotificationController.php` — GET notifications: lista + conteo no leídas. PATCH notifications/{id}/read: marcar leída
    - _Requerimientos: 2.1, 2.2, 2.3, 2.4, 3.1, 3.4, 4.1, 4.5, 5.1, 5.2, 5.3, 5.4, 6.1, 6.6, 7.5, 13.1, 13.3_

  - [ ] 8.3 Registrar rutas Worker en api.php
    - Grupo `v1/worker` con middleware `auth:sanctum` + `EnsureIsWorker`
    - Registrar todos los endpoints según el contrato API del diseño
    - _Requerimientos: 14.6_

  - [ ]* 8.4 Tests de propiedad: Control de acceso y aislamiento
    - **Propiedad 1: Control de acceso basado en personal y rol**
    - **Propiedad 2: Aislamiento de perfil entre trabajadores**
    - Generar usuarios random con/sin personal, verificar acceso correcto. Verificar que trabajador A no puede acceder a perfil de B
    - **Valida: Requerimientos 1.3, 1.4, 2.4**

  - [ ]* 8.5 Tests de propiedad: Crédito R11 y notificaciones
    - **Propiedad 6: Cálculo de crédito R11 disponible**
    - **Propiedad 8: Invariante de conteo de notificaciones no leídas**
    - Generar pares (limite, usado) random, verificar disponible = limite - usado. Generar sets de notificaciones, verificar conteo
    - **Valida: Requerimientos 5.1, 7.5**

- [ ] 9. Controllers y Rutas — Admin API
  - [x] 9.1 Crear Form Requests de validación Admin
    - `Requests/Admin/StorePersonalRequest.php` — validar nombre, rol, sueldos base, activo
    - `Requests/Admin/StoreShiftRequest.php` — validar personal_id, fecha, fecha_fin, tipo, reemplazado_por, monto_reemplazo, pago_por
    - `Requests/Admin/StoreAdjustmentRequest.php` — validar personal_id, mes, monto, concepto, categoria_id, notas
    - _Requerimientos: 8.2, 9.2, 11.1_

  - [x] 9.2 Implementar controllers Admin
    - `Controllers/Admin/PersonalController.php` — GET index, POST store, PUT update, PATCH toggle (activar/desactivar)
    - `Controllers/Admin/ShiftController.php` — GET index (calendario completo), POST store (crear turno/reemplazo, soportar rango de fechas), DELETE destroy
    - `Controllers/Admin/PayrollController.php` — GET index (resumen nómina), POST payments (registrar pago), PUT budget (actualizar presupuesto), POST send-liquidacion (enviar email individual), POST send-all (enviar masivo vía Queue)
    - `Controllers/Admin/AdjustmentController.php` — GET index (ajustes del mes), GET categories, POST store, DELETE destroy
    - `Controllers/Admin/CreditController.php` — GET index (trabajadores con crédito), POST approve, POST reject, POST manual-payment
    - `Controllers/Admin/ShiftSwapController.php` — GET index (solicitudes pendientes), POST approve (crear turnos reemplazo), POST reject
    - _Requerimientos: 8.1-8.5, 9.1-9.5, 10.1-10.6, 11.1-11.4, 12.1-12.5_

  - [ ] 9.3 Registrar rutas Admin en api.php
    - Grupo `v1/admin` con middleware `auth:sanctum` + `EnsureIsWorker` + `EnsureIsAdmin`
    - Registrar todos los endpoints según el contrato API del diseño
    - _Requerimientos: 14.6_

  - [ ]* 9.4 Test de propiedad: Rango de turnos
    - **Propiedad 9: Creación de turnos por rango de fechas**
    - Generar rangos de fechas random, verificar que se crean exactamente (fecha_fin - fecha_inicio + 1) registros
    - **Valida: Requerimiento 9.4**

  - [ ]* 9.5 Test de propiedad: Filtrado compañeros cambio de turno
    - **Propiedad 7: Filtrado de compañeros para cambio de turno**
    - Generar conjuntos de trabajadores con roles y estados variados, verificar que solo se incluyen activos del mismo centro de costo excluyendo al solicitante
    - **Valida: Requerimiento 6.1**

- [ ] 10. Checkpoint — Verificar API completa
  - Asegurar que todos los endpoints responden correctamente, los middleware funcionan y los tests pasan. Preguntar al usuario si hay dudas.


- [ ] 11. Frontend — Autenticación y Layout
  - [ ] 11.1 Implementar middleware.ts de Next.js
    - Control de acceso en el edge: rutas `/admin/*` solo para rol admin/dueño, rutas `/dashboard/*` para cualquier trabajador autenticado
    - Redirect a `/login` si no hay sesión, redirect a `/dashboard` si trabajador accede a `/admin/*`
    - Verificar sesión vía cookie o llamada a `GET /api/v1/auth/me`
    - _Requerimientos: 14.8, 1.1, 1.4_

  - [ ] 11.2 Crear página de login
    - `app/login/page.tsx` — formulario con email/password y botón Google OAuth
    - Llamar a `POST /api/v1/auth/login`, guardar token, redirect a dashboard
    - Mostrar error descriptivo si no es trabajador activo (403)
    - Diseño mobile-first con TailwindCSS
    - _Requerimientos: 1.2, 1.3, 14.9_

  - [ ] 11.3 Crear layouts con sidebar
    - `app/dashboard/layout.tsx` — sidebar trabajador con links: Inicio, Perfil, Turnos, Liquidación, Crédito, Asistencia, Cambios, Notificaciones
    - `app/admin/layout.tsx` — sidebar admin con links: Inicio, Personal, Turnos, Nómina, Ajustes, Créditos, Cambios
    - `components/layouts/WorkerSidebar.tsx` y `AdminSidebar.tsx` — componentes de sidebar con iconos lucide-react
    - Indicador de notificaciones no leídas en sidebar
    - Mobile-first: sidebar colapsable en móvil
    - _Requerimientos: 14.1, 14.9, 7.5_

  - [ ] 11.4 Crear página raíz
    - `app/page.tsx` — redirect a `/login` o `/dashboard` según estado de sesión
    - `app/layout.tsx` — layout global con fuentes, metadata, providers
    - _Requerimientos: 1.1_

- [ ] 12. Frontend — Páginas del Trabajador
  - [ ] 12.1 Dashboard del trabajador
    - `app/dashboard/page.tsx` — resumen: turnos de hoy, crédito R11 disponible, notificaciones recientes, alertas
    - _Requerimientos: 14.9_

  - [ ] 12.2 Página de perfil
    - `app/dashboard/perfil/page.tsx` — mostrar nombre, email, teléfono, RUT, roles, foto, fecha registro, sueldos base por rol
    - Sección de Crédito R11 si es_credito_r11=1: límite, usado, disponible, estado
    - Banner de alerta si crédito bloqueado
    - _Requerimientos: 2.1, 2.2, 2.3, 5.3_

  - [ ] 12.3 Página de turnos / calendario
    - `app/dashboard/turnos/page.tsx` — calendario mensual con días de trabajo marcados
    - Diferenciar visualmente: turnos normales, reemplazos realizados, reemplazos recibidos
    - Mostrar detalle al tocar un día: quién reemplaza, monto
    - Navegación entre meses (anterior/siguiente)
    - Si tiene roles en ambos centros de costo, mostrar ambos calendarios
    - _Requerimientos: 3.1, 3.2, 3.4, 3.5_

  - [ ] 12.4 Página de liquidación
    - `app/dashboard/liquidacion/page.tsx` — desglose por centro de costo
    - Mostrar: sueldo base, días trabajados, reemplazos realizados (con monto), reemplazos recibidos (con monto), ajustes (concepto, categoría, monto, notas)
    - Descuento Crédito R11 con etiqueta especial
    - Gran total sumando todos los centros de costo
    - Navegación entre meses
    - _Requerimientos: 4.1, 4.2, 4.3, 4.5, 4.6_

  - [ ] 12.5 Página de crédito R11
    - `app/dashboard/credito/page.tsx` — estado del crédito: límite, usado, disponible, relación R11, fecha aprobación
    - Historial de transacciones ordenado por fecha descendente
    - Banner si crédito bloqueado
    - Mensaje si no tiene crédito R11 activo
    - _Requerimientos: 5.1, 5.2, 5.3, 5.4_

  - [ ] 12.6 Página de asistencia
    - `app/dashboard/asistencia/page.tsx` — resumen mensual: días normales, reemplazos realizados, días trabajados, días reemplazado, por centro de costo
    - Navegación entre meses
    - _Requerimientos: 13.1, 13.2, 13.3_

  - [ ] 12.7 Página de solicitudes de cambio de turno
    - `app/dashboard/cambios/page.tsx` — formulario: seleccionar día con turno, elegir compañero (filtrado por centro de costo), motivo
    - Historial de solicitudes con estado (pendiente, aprobada, rechazada)
    - _Requerimientos: 6.1, 6.2, 6.6_

  - [ ] 12.8 Página de notificaciones
    - `app/dashboard/notificaciones/page.tsx` — lista de notificaciones con tipo, título, mensaje, fecha
    - Marcar como leída al hacer click
    - Indicador de no leídas
    - _Requerimientos: 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ] 13. Checkpoint — Verificar frontend trabajador
  - Asegurar que todas las páginas del trabajador renderizan correctamente y consumen la API. Preguntar al usuario si hay dudas.


- [ ] 14. Frontend — Páginas del Admin
  - [ ] 14.1 Dashboard admin
    - `app/admin/page.tsx` — resumen: total nómina del mes, solicitudes pendientes, alertas de crédito R11
    - _Requerimientos: 14.9_

  - [ ] 14.2 Gestión de personal
    - `app/admin/personal/page.tsx` — tabla con todos los trabajadores: nombre, roles, sueldos, estado, vinculación usuario
    - Modal/formulario para agregar, editar trabajador
    - Botón activar/desactivar
    - _Requerimientos: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ] 14.3 Gestión de turnos
    - `app/admin/turnos/page.tsx` — calendario mensual con todos los trabajadores
    - Diferenciar visualmente turnos normales, seguridad, reemplazos
    - Modal para asignar turno: seleccionar trabajador, tipo, rango de fechas, reemplazante, monto, quién paga
    - Eliminar turno existente
    - Indicador de solicitudes de cambio pendientes
    - _Requerimientos: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ] 14.4 Gestión de nómina y liquidaciones
    - `app/admin/nomina/page.tsx` — resumen por centro de costo: presupuesto, total sueldos, total pagado, diferencia
    - Tarjeta por trabajador: nombre, rol, sueldo base, días, reemplazos, ajustes, total liquidación
    - Registrar pago de nómina
    - Actualizar presupuesto
    - Botón enviar liquidación por email (individual y masivo)
    - Indicador de progreso para envío masivo
    - _Requerimientos: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

  - [ ] 14.5 Gestión de ajustes de sueldo
    - `app/admin/ajustes/page.tsx` — lista de ajustes del mes agrupados por trabajador
    - Formulario para crear ajuste: trabajador, mes, monto, concepto, categoría, notas
    - Eliminar ajuste
    - _Requerimientos: 11.1, 11.2, 11.3, 11.4_

  - [ ] 14.6 Gestión de créditos R11
    - `app/admin/creditos/page.tsx` — lista de trabajadores con crédito R11: límite, usado, disponible, estado
    - Acciones: aprobar, rechazar, pago manual
    - _Requerimientos: 12.1_

  - [ ] 14.7 Gestión de solicitudes de cambio
    - `app/admin/cambios/page.tsx` — lista de solicitudes pendientes con detalle
    - Botones aprobar/rechazar
    - _Requerimientos: 6.3, 6.4, 6.5, 9.5_

- [ ] 15. Checkpoint — Verificar frontend admin
  - Asegurar que todas las páginas admin renderizan correctamente y consumen la API. Preguntar al usuario si hay dudas.

- [ ] 16. Cron Jobs y Automatización
  - [ ] 16.1 Crear comando R11AutoDeductCommand
    - `Console/Commands/R11AutoDeductCommand.php` — ejecuta R11CreditService::autoDeduct()
    - Registrar en `routes/console.php` con schedule `->monthlyOn(1, '06:00')` (día 1 a las 6am)
    - Log de resultados y advertencias
    - _Requerimientos: 12.1, 12.2, 12.3, 12.4, 12.5_

  - [ ] 16.2 Crear comando R11ReminderCommand
    - `Console/Commands/R11ReminderCommand.php` — enviar recordatorio a trabajadores con deuda R11 el día 28
    - Registrar en `routes/console.php` con schedule `->monthlyOn(28, '10:00')`
    - Crear notificación en notificaciones_mi3 para cada deudor
    - _Requerimientos: 14.5_

  - [ ] 16.3 Configurar Laravel Queue para emails
    - Configurar driver de queue (database o redis)
    - Crear job `SendLiquidacionEmailJob` que usa GmailService
    - Configurar reintentos (3 intentos con backoff exponencial)
    - _Requerimientos: 10.6_

- [ ] 17. Checkpoint — Verificar cron jobs y queue
  - Asegurar que los comandos están registrados en el scheduler, los jobs se encolan correctamente. Preguntar al usuario si hay dudas.

- [ ] 18. Tests unitarios
  - [ ]* 18.1 Tests unitarios de autenticación
    - Login con email/password, login con Google OAuth, logout, token inválido, usuario no trabajador
    - _Requerimientos: 1.1, 1.2, 1.3, 1.6_

  - [ ]* 18.2 Tests unitarios de ShiftService
    - Turnos del mes actual, turnos dinámicos generados correctamente, reemplazos visibles, navegación entre meses
    - _Requerimientos: 3.1, 3.3_

  - [ ]* 18.3 Tests unitarios de LiquidacionService
    - Liquidación mes actual, con ajustes, con reemplazos, con descuento R11, mes anterior, trabajador con roles duales
    - _Requerimientos: 4.1, 4.4, 4.6_

  - [ ]* 18.4 Tests unitarios de R11CreditService
    - Auto-deducción con deudores, sin deudores, usuario sin personal vinculado, crédito bloqueado que se desbloquea
    - _Requerimientos: 12.1, 12.2, 12.3, 12.5_

  - [ ]* 18.5 Tests unitarios de controllers Admin
    - CRUD personal, crear/eliminar turnos, registrar pago, crear/eliminar ajuste, aprobar/rechazar solicitud
    - _Requerimientos: 8.1-8.5, 9.1-9.3, 10.1-10.4, 11.1-11.4_

  - [ ]* 18.6 Tests unitarios de middleware
    - EnsureIsWorker: con token válido, sin token, usuario sin personal, personal inactivo
    - EnsureIsAdmin: con rol admin, con rol dueño, sin rol admin
    - _Requerimientos: 1.3, 1.4, 1.6_

- [ ] 19. Checkpoint final — Verificar todo
  - Asegurar que todos los tests pasan, ambas apps compilan, los endpoints responden correctamente. Preguntar al usuario si hay dudas antes de considerar la implementación completa.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requerimientos específicos para trazabilidad
- Los checkpoints permiten validación incremental
- Los tests de propiedad validan propiedades universales de correctitud definidas en el diseño
- Los tests unitarios validan ejemplos específicos y edge cases
- El backend debe estar funcional antes de implementar las páginas frontend correspondientes
