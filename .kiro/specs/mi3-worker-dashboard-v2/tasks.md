# Plan de Implementación: mi3 Worker Dashboard v2

## Resumen

Implementación incremental del dashboard v2 del trabajador: modelo de datos de préstamos, servicio de lógica de negocio, endpoints REST (worker + admin), comando cron de auto-descuento, dashboard rediseñado con 4 tarjetas, páginas de préstamos y reemplazos, y navegación actualizada.

## Tareas

- [ ] 1. Base de datos y modelo Prestamo
  - [x] 1.1 Crear migración para tabla `prestamos` y seed de categoría 'prestamo' en `ajustes_categorias`
    - Crear migración Laravel con la estructura definida en el diseño (columnas, índices, FKs)
    - Agregar INSERT en `ajustes_categorias` con nombre "Cuota Préstamo", slug "prestamo", ícono "💰"
    - _Requerimientos: 2.1, 2.2, 2.3_
  - [x] 1.2 Crear modelo `Prestamo` en `mi3/backend/app/Models/Prestamo.php`
    - Definir $table, $fillable, $casts, relaciones (personal, aprobadoPor)
    - Agregar relación `prestamos()` en modelo `Personal`
    - _Requerimientos: 2.1_

- [ ] 2. LoanService — lógica de negocio
  - [x] 2.1 Crear `mi3/backend/app/Services/Loan/LoanService.php` con métodos principales
    - `solicitarPrestamo()`: validar no préstamo activo, monto <= sueldo base, cuotas 1-3, crear registro pendiente, notificar admin
    - `aprobar()`: actualizar estado, crear ajuste positivo en ajustes_sueldo con categoría 'prestamo', notificar trabajador
    - `rechazar()`: actualizar estado, notificar trabajador
    - `getPrestamoActivo()`, `getPrestamosPorPersonal()`, `getTodosPrestamos()`
    - `getSueldoBase()`: calcular sueldo base del trabajador según rol principal
    - _Requerimientos: 3.3, 3.4, 3.5, 3.6, 4.2, 4.3, 4.4, 4.5, 10.2_
  - [x] 2.2 Test de propiedad: validación de monto de préstamo
    - **Propiedad 2: Validación de monto de préstamo**
    - Generar montos aleatorios y sueldo base, verificar que solo se aceptan montos > 0 y <= sueldo base
    - **Valida: Requerimientos 3.4, 10.2**
  - [x] 2.3 Test de propiedad: préstamo activo impide nueva solicitud
    - **Propiedad 3: Préstamo activo impide nueva solicitud**
    - Generar estados de préstamo aleatorios, verificar que solo se bloquea cuando hay préstamo aprobado con cuotas pendientes
    - **Valida: Requerimientos 3.5, 10.2**
  - [x] 2.4 Test de propiedad: aprobación crea registros correctos
    - **Propiedad 4: Aprobación de préstamo crea registros correctos**
    - Generar préstamos pendientes con montos aleatorios, verificar estado, fecha, ajuste positivo
    - **Valida: Requerimientos 4.2, 4.3, 10.4**

- [ ] 3. LoanAutoDeductCommand — cron de descuento mensual
  - [x] 3.1 Crear `mi3/backend/app/Console/Commands/LoanAutoDeductCommand.php`
    - Implementar `procesarDescuentosMensuales()` en LoanService
    - Consultar préstamos aprobados con cuotas pendientes y fecha_inicio_descuento <= mes actual
    - Crear ajuste negativo por cuota (monto_aprobado / cuotas, redondeado)
    - Incrementar cuotas_pagadas, cambiar estado a 'pagado' si completa
    - Usar transacción DB por préstamo, continuar si uno falla
    - Registrar comando en Kernel/schedule
    - _Requerimientos: 5.1, 5.2, 5.3, 5.4, 5.5_
  - [x] 3.2 Test de propiedad: auto-descuento mensual
    - **Propiedad 5: Auto-descuento mensual procesa préstamos correctamente**
    - Generar conjuntos de préstamos con diferentes cuotas/montos, verificar ajustes negativos y actualización de estado
    - **Valida: Requerimientos 5.1, 5.2, 5.3, 5.4**

- [ ] 4. Checkpoint — Backend core
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Controllers backend (Worker + Admin)
  - [x] 5.1 Crear `Worker/LoanController` con endpoints `index()` y `store()`
    - GET /api/v1/worker/loans — lista préstamos del trabajador autenticado
    - POST /api/v1/worker/loans — crea solicitud con validación
    - _Requerimientos: 3.1, 3.2, 3.3, 10.1, 10.2_
  - [x] 5.2 Crear `Worker/DashboardController` con endpoint `index()`
    - GET /api/v1/worker/dashboard-summary — retorna sueldo, préstamo activo, descuentos por categoría, reemplazos del mes
    - Usar LiquidacionService existente para sueldo, LoanService para préstamo, queries para descuentos y reemplazos
    - _Requerimientos: 6.1, 6.2, 6.3, 6.4, 6.5, 10.6_
  - [x] 5.3 Crear `Worker/ReplacementController` con endpoint `index()`
    - GET /api/v1/worker/replacements?mes=YYYY-MM — retorna realizados, recibidos y resumen
    - Filtrar turnos tipo 'reemplazo'/'reemplazo_seguridad' del mes
    - _Requerimientos: 8.1, 8.2, 8.4_
  - [x] 5.4 Crear `Admin/LoanController` con endpoints `index()`, `approve()`, `reject()`
    - GET /api/v1/admin/loans — lista todos los préstamos
    - POST /api/v1/admin/loans/{id}/approve — aprueba con monto, fecha inicio, notas
    - POST /api/v1/admin/loans/{id}/reject — rechaza con notas
    - _Requerimientos: 4.1, 4.2, 4.3, 4.4, 4.5, 10.3, 10.4, 10.5_
  - [x] 5.5 Registrar nuevas rutas en `mi3/backend/routes/api.php`
    - Agregar rutas worker: loans, dashboard-summary, replacements
    - Agregar rutas admin: loans, loans/{id}/approve, loans/{id}/reject
    - _Requerimientos: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_
  - [x] 5.6 Modificar `Admin/PersonalController::store()` para sueldo base por defecto
    - Aplicar $300.000 cuando campos de sueldo son null o 0 según roles seleccionados
    - _Requerimientos: 1.1, 1.2, 1.4_
  - [x] 5.7 Test de propiedad: asignación de sueldo base por defecto
    - **Propiedad 1: Asignación de sueldo base por defecto**
    - Generar roles y sueldos aleatorios, verificar que null/0 → $300.000 y valores explícitos se preservan
    - **Valida: Requerimientos 1.1, 1.2, 1.4**
  - [x] 5.8 Test de propiedad: cálculo de resumen de préstamo activo
    - **Propiedad 6: Cálculo de resumen de préstamo activo**
    - Generar préstamos con diferentes cuotas pagadas, verificar monto pendiente, cuotas restantes, monto cuota
    - **Valida: Requerimientos 6.2, 7.3**
  - [x] 5.9 Test de propiedad: agregación de descuentos por categoría
    - **Propiedad 7: Agregación de descuentos por categoría**
    - Generar ajustes negativos con categorías aleatorias, verificar que total = suma y desglose es correcto
    - **Valida: Requerimiento 6.3**
  - [x] 5.10 Test de propiedad: cálculo de resumen de reemplazos
    - **Propiedad 8: Cálculo de resumen de reemplazos**
    - Generar turnos de reemplazo aleatorios, verificar balance = realizados - recibidos
    - **Valida: Requerimientos 6.4, 8.2**
  - [x] 5.11 Test de propiedad: filtrado de reemplazos por mes
    - **Propiedad 9: Filtrado de reemplazos por mes**
    - Generar turnos en múltiples meses, verificar que solo se retornan los del mes consultado
    - **Valida: Requerimiento 8.4**

- [ ] 6. Checkpoint — Backend completo
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Frontend — Tipos e interfaces
  - [x] 7.1 Agregar tipos TypeScript en `mi3/frontend/types/index.ts`
    - Interfaces: Prestamo, DashboardSummary, ReplacementData, ReplacementSummary
    - _Requerimientos: 6.5, 7.1, 8.4_

- [ ] 8. Frontend — Dashboard rediseñado
  - [x] 8.1 Rediseñar `mi3/frontend/app/dashboard/page.tsx` con 4 tarjetas de resumen
    - Tarjeta Sueldo: total liquidación del mes
    - Tarjeta Préstamos: monto pendiente, cuotas restantes, monto cuota (o "$0")
    - Tarjeta Descuentos: total + desglose por categoría
    - Tarjeta Reemplazos: realizados (cantidad + monto) y recibidos (cantidad + monto)
    - Mantener secciones existentes de turnos del día y notificaciones debajo
    - Consumir GET /api/v1/worker/dashboard-summary
    - Skeleton/spinner por tarjeta durante carga
    - _Requerimientos: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [ ] 9. Frontend — Página de Préstamos
  - [x] 9.1 Crear `mi3/frontend/app/dashboard/prestamos/page.tsx`
    - Lista de préstamos con estado, monto, cuotas, fecha
    - Barra de progreso para préstamos activos (cuotas pagadas / total)
    - Monto próxima cuota y fecha estimada de descuento
    - Botón "Solicitar Préstamo" (deshabilitado si hay préstamo activo)
    - Formulario modal: monto (numérico), cuotas (selector 1-3), motivo (opcional)
    - Estado vacío: "No tienes préstamos" + botón solicitar
    - Consumir GET /api/v1/worker/loans y POST /api/v1/worker/loans
    - _Requerimientos: 3.1, 3.2, 3.4, 3.5, 7.1, 7.2, 7.3, 7.4, 7.5_

- [ ] 10. Frontend — Página de Reemplazos
  - [x] 10.1 Crear `mi3/frontend/app/dashboard/reemplazos/page.tsx`
    - Sección "Reemplazos que hice": lista con fecha, titular, monto, pago_por
    - Sección "Me reemplazaron": lista con fecha, reemplazante, monto, pago_por
    - Resumen mensual: total ganado, total descontado, balance neto
    - Navegación entre meses (anterior/siguiente)
    - Enlace a /dashboard/cambios para solicitar reemplazo
    - Consumir GET /api/v1/worker/replacements?mes=YYYY-MM
    - _Requerimientos: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 11. Frontend — Navegación actualizada
  - [x] 11.1 Actualizar `mi3/frontend/lib/navigation.ts`
    - Primary nav: Inicio, Turnos, Sueldo, Préstamos (reemplaza Crédito)
    - Secondary nav: Perfil, Crédito, Reemplazos, Asistencia, Cambios, Notificaciones
    - _Requerimientos: 9.1, 9.2, 9.4_
  - [x] 11.2 Agregar badge de préstamo pendiente en navegación
    - Mostrar indicador visual cuando hay préstamo con estado 'pendiente'
    - Modificar componente de navegación para consumir estado de préstamo
    - _Requerimiento: 9.3_
  - [x] 11.3 Test de propiedad: préstamos ordenados por fecha descendente
    - **Propiedad 10: Préstamos ordenados por fecha descendente**
    - Generar préstamos con fechas aleatorias, verificar orden descendente en respuesta
    - **Valida: Requerimiento 7.4**

- [ ] 12. Formulario admin — Sueldo base por defecto en frontend
  - [x] 12.1 Modificar formulario de creación de personal en admin para pre-rellenar sueldo base con $300.000
    - Pre-rellenar campo de sueldo base con $300.000 para cada rol seleccionado
    - Permitir que el admin modifique el valor
    - _Requerimientos: 1.3_

- [ ] 13. Push Notifications
  - [x] 13.1 Crear tabla `push_subscriptions_mi3` en BD y migración Laravel
    - Columnas: id, personal_id (FK), subscription (JSON), is_active, created_at, updated_at
    - _Requerimientos: 11.3_
  - [x] 13.2 Instalar `minishlink/web-push` y configurar VAPID keys
    - Agregar dependencia al composer.json de mi3/backend
    - Generar VAPID keys y agregar como env vars en Coolify
    - _Requerimientos: 11.5_
  - [x] 13.3 Crear `PushNotificationService` en `mi3/backend/app/Services/Notification/PushNotificationService.php`
    - Métodos: enviar(personalId, titulo, cuerpo, url, prioridad), suscribir(personalId, subscription), desactivarExpiradas()
    - Usa web-push con VAPID keys de env
    - _Requerimientos: 11.6, 11.7, 11.8_
  - [x] 13.4 Crear `Worker/PushController` con endpoint `POST /api/v1/worker/push/subscribe`
    - Guarda suscripción push del trabajador autenticado
    - _Requerimientos: 11.4_
  - [x] 13.5 Integrar push en LoanService y ShiftSwapService
    - Enviar push al aprobar/rechazar préstamo, al aprobar cambio de turno, al asignar reemplazo
    - Enviar push al admin cuando trabajador solicita préstamo o cambio
    - _Requerimientos: 11.6, 11.7_
  - [x] 13.6 Crear Service Worker `mi3/frontend/public/sw.js`
    - Escuchar evento push, mostrar notificación nativa, manejar click para abrir URL
    - _Requerimientos: 11.1, 11.9_
  - [x] 13.7 Crear hook `usePushNotifications` en `mi3/frontend/hooks/usePushNotifications.ts`
    - Solicitar permiso, suscribir via pushManager, enviar subscription al backend
    - Se ejecuta al montar el layout del dashboard
    - _Requerimientos: 11.2, 11.10_
  - [x] 13.8 Integrar notificaciones in-app con push
    - Cuando NotificationService crea notificación en BD, también llama a PushNotificationService
    - Badge en MobileHeader se actualiza al navegar
    - _Requerimientos: 12.1, 12.2, 12.3_

- [ ] 14. Checkpoint final
  - Ensure all tests pass, ask the user if questions arise.

## Notas

- Todos los tests (unitarios y de propiedad) son obligatorios
- Cada tarea referencia requerimientos específicos para trazabilidad
- Los checkpoints permiten validación incremental
- Los tests de propiedad validan propiedades universales de correctitud definidas en el diseño
- Push notifications requieren VAPID keys configuradas en Coolify y PWA instalada en el dispositivo
