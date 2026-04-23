# Implementation Plan: Admin Credits & Users Tabs

## Overview

Reorganizar las secciones Créditos y Usuarios del panel admin de mi3. Se implementan tabs en ambas secciones, nuevos endpoints backend para RL6 y clientes, integración de emails de cobranza, y una franja de métricas contextuales. El plan sigue un orden incremental: primero backend (modelos, servicios, controladores, rutas), luego frontend (navegación, componentes con tabs, modales, métricas), y finalmente integración y testing.

## Tasks

- [x] 1. Backend: Modelo Usuario y RL6CreditService
  - [x] 1.1 Actualizar modelo Usuario.php con campos RL6 en $fillable
    - Agregar campos `es_militar_rl6`, `credito_aprobado`, `limite_credito`, `credito_usado`, `credito_bloqueado`, `grado_militar`, `unidad_trabajo`, `rut`, `fecha_ultimo_pago` al array `$fillable` en `mi3/backend/app/Models/Usuario.php`
    - Agregar relaciones Eloquent: `rl6Transactions()` hasMany a `rl6_credit_transactions`, `emailLogs()` hasMany a `email_logs`
    - _Requirements: 3.1, 3.5_

  - [x] 1.2 Crear RL6CreditService.php
    - Crear `mi3/backend/app/Services/RL6CreditService.php`
    - Implementar `getRL6Users()`: query usuarios con `es_militar_rl6=1` AND `credito_aprobado=1`, join con `rl6_credit_transactions` y `email_logs`, calcular `es_moroso`, `dias_mora`, `deuda_ciclo_vencido`, `pagado_este_mes`, `ultimo_email_enviado`, `ultimo_email_tipo`, `disponible`
    - Implementar `getSummary(Collection $users)`: calcular `total_usuarios`, `total_credito_otorgado`, `total_deuda_actual`, `total_morosos`, `total_deuda_morosos`, `pagos_del_mes_count`, `pagos_del_mes_monto`, `tasa_cobro`
    - Implementar `calculateMoroso()`: lógica de ciclo vencido (día 22 mes anterior → día 21 mes actual), verificar `fecha_ultimo_pago` no es del mes actual
    - Implementar `approveCredit(int $id, float $limite)`: set `credito_aprobado=1`, `limite_credito=$limite`
    - Implementar `rejectCredit(int $id)`: set `credito_aprobado=0`, `limite_credito=0`
    - Implementar `manualPayment(int $id, float $monto, ?string $desc)`: crear transacción `refund` en `rl6_credit_transactions`, reducir `credito_usado` con `max(0, old - monto)`, actualizar `fecha_ultimo_pago`, desbloquear si `credito_usado` llega a 0
    - Implementar `calculateEmailEstado(array $userData)`: retornar `sin_deuda`, `recordatorio`, `urgente`, o `moroso` según lógica del design
    - Implementar `previewEmail(int $id)`: generar HTML del email según estado, retornar `{html, tipo, email}`
    - Implementar `buildEmailHtml(array $user, string $tipo)`: replicar lógica de `caja3/api/gmail/preview_email_dynamic.php` con diseño responsive y colores según Email_Estado
    - Referencia: `caja3/api/gmail/get_rl6_users.php` para lógica de morosidad, `caja3/api/gmail/preview_email_dynamic.php` para HTML de emails
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 9.1, 9.2_

  - [ ]* 1.3 Write property test: RL6 Summary aggregation correctness
    - **Property 1: RL6 Summary aggregation correctness**
    - Generar conjuntos aleatorios de usuarios RL6 con `limite_credito`, `credito_usado`, y estados moroso variados
    - Verificar que `getSummary()` produce totales correctos para todas las métricas
    - Mínimo 100 iteraciones con PHPUnit data providers
    - **Validates: Requirements 3.2**

  - [ ]* 1.4 Write property test: Manual payment arithmetic correctness
    - **Property 2: Manual payment arithmetic correctness**
    - Generar usuarios con `credito_usado >= 0` y `credito_bloqueado` en {0,1}, y montos de pago `> 0`
    - Verificar que `new credito_usado = max(0, old - monto)`, transacción refund existe, `fecha_ultimo_pago` es hoy, desbloqueo condicional
    - Mínimo 100 iteraciones con PHPUnit data providers
    - **Validates: Requirements 3.5, 3.6**

  - [ ]* 1.5 Write property test: Moroso calculation correctness
    - **Property 3: Moroso calculation correctness**
    - Generar fechas y usuarios con transacciones de débito y `fecha_ultimo_pago` variados
    - Verificar las 4 condiciones de morosidad del design: día <= 21, deuda_ciclo_vencido == 0, pagó este mes, moroso real
    - Mínimo 100 iteraciones con PHPUnit data providers
    - **Validates: Requirements 3.7, 3.8**

  - [ ]* 1.6 Write property test: Email Estado classification correctness
    - **Property 4: Email Estado classification correctness**
    - Generar usuarios con `credito_usado`, `fecha_ultimo_pago`, `deuda_ciclo_vencido` variados y diferentes días del mes
    - Verificar clasificación correcta: `sin_deuda`, `recordatorio`, `urgente`, `moroso`
    - Mínimo 100 iteraciones con PHPUnit data providers
    - **Validates: Requirements 9.2**

- [x] 2. Backend: Extender CreditController y crear UserController
  - [x] 2.1 Extender CreditController.php con métodos RL6
    - Agregar métodos en `mi3/backend/app/Http/Controllers/Admin/CreditController.php`:
    - `rl6Index()`: inyectar RL6CreditService, retornar `{data, summary}` como JSON
    - `rl6Approve(Request $request, int $id)`: validar `limite` en request, llamar service
    - `rl6Reject(int $id)`: llamar service
    - `rl6ManualPayment(Request $request, int $id)`: validar `monto > 0`, llamar service
    - `rl6PreviewEmail(int $id)`: llamar service `previewEmail()`, retornar `{html, tipo, email}`
    - `rl6SendEmail(int $id)`: llamar GmailService `sendRL6CollectionEmail()`, loguear en email_logs
    - `rl6SendBulkEmails(Request $request)`: aceptar array de user IDs, enviar secuencialmente, retornar `{total_sent, total_failed, failed: [...]}`
    - _Requirements: 3.1, 3.3, 3.4, 3.5, 9.1, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8_

  - [x] 2.2 Crear UserController.php
    - Crear `mi3/backend/app/Http/Controllers/Admin/UserController.php`
    - Implementar `customers(Request $request)`: query `usuarios` con left join a `tuu_orders` (payment_status='paid'), calcular `total_orders`, `total_spent`, `last_order_date`, soporte para `?search=` con LIKE en nombre/email, ordenar por `id DESC`
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 2.3 Extender GmailService.php con método RL6
    - Agregar método `sendRL6CollectionEmail(int $userId, string $email, string $html, string $subject, string $emailType)` en `mi3/backend/app/Services/Email/GmailService.php`
    - Reutilizar `getValidToken()` y `sendEmail()` existentes
    - Insertar registro en `email_logs` con status 'sent' o 'failed'
    - Retornar `{success, gmail_message_id, error?}`
    - _Requirements: 9.3, 9.5, 9.6, 9.7_

  - [x] 2.4 Registrar rutas API en routes/api.php
    - Agregar rutas dentro del grupo admin en `mi3/backend/routes/api.php`:
    - `POST credits/rl6/send-bulk-emails` (ruta estática ANTES de rutas con {id})
    - `GET credits/rl6`
    - `POST credits/rl6/{id}/approve`
    - `POST credits/rl6/{id}/reject`
    - `POST credits/rl6/{id}/manual-payment`
    - `GET credits/rl6/{id}/preview-email`
    - `POST credits/rl6/{id}/send-email`
    - `GET users/customers`
    - _Requirements: 3.1, 3.3, 3.4, 3.5, 6.1, 9.1, 9.3, 9.4_

  - [ ]* 2.5 Write property test: Customer search filtering and ordering
    - **Property 5: Customer search filtering and ordering**
    - Generar conjuntos de usuarios con nombres/emails aleatorios y órdenes con payment_status variados
    - Verificar que búsqueda filtra correctamente, `total_orders` y `total_spent` son correctos, orden es `id DESC`
    - Mínimo 100 iteraciones con PHPUnit data providers
    - **Validates: Requirements 6.2, 6.3, 6.4**

- [x] 3. Checkpoint - Backend completo
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Frontend: Navegación y labels
  - [x] 4.1 Actualizar AdminSidebarSPA.tsx
    - Cambiar label de `creditos` de 'Créditos R11' a 'Créditos' en el array `links` de `mi3/frontend/components/admin/AdminSidebarSPA.tsx`
    - Cambiar label de `personal` de 'Personal' a 'Usuarios'
    - _Requirements: 1.1, 4.1_

  - [x] 4.2 Actualizar MobileBottomNavSPA.tsx
    - Cambiar label de `creditos` de 'Créditos R11' a 'Créditos' en `primaryItems`/`secondaryItems` de `mi3/frontend/components/admin/MobileBottomNavSPA.tsx`
    - Cambiar label de `personal` de 'Personal' a 'Usuarios'
    - _Requirements: 1.2, 4.2_

  - [x] 4.3 Actualizar AdminShell.tsx SECTION_TITLES
    - Cambiar `creditos: 'Créditos R11'` a `creditos: 'Créditos'` en `SECTION_TITLES` de `mi3/frontend/components/admin/AdminShell.tsx`
    - Cambiar `personal: 'Personal'` a `personal: 'Usuarios'`
    - _Requirements: 1.3, 4.3_

- [x] 5. Frontend: TypeScript interfaces y utilidades
  - [x] 5.1 Crear interfaces TypeScript para RL6 y Customers
    - Crear o agregar en archivo de tipos las interfaces: `RL6CreditUser`, `RL6Summary`, `CustomerUser`, `EmailEstado` type según el design document
    - Crear función `formatCLP(value: number): string` que formatea valores monetarios con "$" y separadores de punto cada 3 dígitos
    - _Requirements: 3.1, 6.1, 10.6_

  - [ ]* 5.2 Write property test: CLP formatting correctness
    - **Property 7: CLP formatting correctness**
    - Generar valores numéricos no negativos (enteros y floats)
    - Verificar que `formatCLP()` empieza con "$", tiene separadores de punto correctos, y parsear el resultado devuelve el valor entero original
    - Usar fast-check con vitest, mínimo 100 iteraciones
    - **Validates: Requirements 10.6**

- [x] 6. Frontend: CreditosSection con tabs RL6
  - [x] 6.1 Modificar CreditosSection.tsx para sistema de tabs
    - Agregar state: `activeTab` ('r11' | 'rl6'), `rl6Data`, `rl6Summary`, `rl6Loading`
    - Registrar tabs via `onHeaderConfig` con keys 'r11' y 'rl6', labels 'Créditos R11' y 'Créditos RL6'
    - Default tab: 'r11'
    - Tab R11: mantener tabla existente sin cambios
    - Tab RL6: nueva tabla con columnas Nombre, RUT, Grado, Límite, Usado, Disponible, Estado Mora, Días Mora, Último Email, Acciones
    - Fetch RL6 data con `apiFetch` al endpoint `GET /admin/credits/rl6` cuando se activa tab RL6 (lazy load)
    - Preservar data de cada tab en state independiente al cambiar tabs
    - Referencia de patrón de tabs: `mi3/frontend/components/admin/sections/ComprasSection.tsx`
    - _Requirements: 2.1, 2.2, 2.3, 2.7, 2.8_

  - [x] 6.2 Implementar badges de estado mora en tab RL6
    - Badge rojo "Moroso" con días de mora cuando `es_moroso=true`
    - Badge naranja "Con deuda" cuando `credito_usado > 0` y no moroso
    - Badge verde "Al día" cuando `credito_usado == 0`
    - _Requirements: 2.4, 2.5, 2.6_

  - [x] 6.3 Implementar acciones RL6: approve, reject, manual-payment
    - Botón aprobar: POST `/admin/credits/rl6/{id}/approve` con input de límite
    - Botón rechazar: POST `/admin/credits/rl6/{id}/reject`
    - Botón pago manual: POST `/admin/credits/rl6/{id}/manual-payment` con input de monto
    - Actualizar data local tras cada acción sin refetch completo
    - _Requirements: 3.3, 3.4, 3.5, 3.6_

- [x] 7. Frontend: Email de cobranza (preview, envío, bulk)
  - [x] 7.1 Crear EmailPreviewModal.tsx
    - Crear `mi3/frontend/components/admin/EmailPreviewModal.tsx`
    - Props: `open`, `onClose`, `onConfirm`, `html`, `emailTipo`, `recipientEmail`, `sending`
    - Renderizar HTML del email en iframe o dangerouslySetInnerHTML
    - Mostrar tipo de email (sin_deuda/recordatorio/urgente/moroso) con color correspondiente
    - Botones: "Cancelar" y "Enviar Email" con loading state
    - _Requirements: 8.2, 8.3_

  - [x] 7.2 Implementar botón "Enviar Email" y flujo de preview/envío en CreditosSection
    - Mostrar botón "Enviar Email" en columna Acciones para usuarios con `credito_usado > 0`
    - Al click: GET `/admin/credits/rl6/{id}/preview-email`, abrir EmailPreviewModal con el HTML
    - Al confirmar: POST `/admin/credits/rl6/{id}/send-email`, mostrar toast success/error
    - Actualizar columna "Último Email" tras envío exitoso sin refetch completo
    - _Requirements: 8.1, 8.2, 8.3, 8.8_

  - [x] 7.3 Implementar bulk action "Cobrar a Morosos"
    - Mostrar botón "Cobrar a Morosos" arriba de la tabla cuando hay al menos un moroso
    - Al click: mostrar diálogo de confirmación con conteo de morosos (BulkConfirmModal o dialog simple)
    - Al confirmar: POST `/admin/credits/rl6/send-bulk-emails` con IDs de morosos
    - Mostrar progreso y resumen final con success/failure counts y nombres de usuarios fallidos
    - _Requirements: 8.4, 8.5, 8.6, 8.7_

- [x] 8. Frontend: PersonalSection con tabs Clientes
  - [x] 8.1 Modificar PersonalSection.tsx para sistema de tabs
    - Agregar state: `activeTab` ('work' | 'clientes'), `clientesData`, `clientesLoading`, `searchQuery`
    - Registrar tabs via `onHeaderConfig` con keys 'work' y 'clientes', labels 'Ruta 11 Work' y 'Clientes'
    - Default tab: 'work'
    - Tab Work: mantener tabla existente sin cambios
    - Tab Clientes: nueva tabla con columnas Nombre, Email, Teléfono, Pedidos, Total Gastado, Último Pedido, Estado
    - Fetch clientes data con `apiFetch` al endpoint `GET /admin/users/customers` cuando se activa tab Clientes (lazy load)
    - Preservar data de cada tab en state independiente
    - Referencia de patrón de tabs: `mi3/frontend/components/admin/sections/ComprasSection.tsx`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

  - [x] 8.2 Implementar búsqueda en tab Clientes
    - Input de búsqueda arriba de la tabla, visible solo en tab Clientes
    - Debounce de 300ms antes de enviar request con `?search=query`
    - Mostrar "No se encontraron clientes" cuando no hay resultados
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

- [x] 9. Frontend: CreditSummaryTrailing (franja de métricas)
  - [x] 9.1 Crear CreditSummaryTrailing.tsx
    - Crear `mi3/frontend/components/admin/CreditSummaryTrailing.tsx`
    - Props: `activeTab`, `rl6Summary`, `r11Data`, `loading`
    - Tab RL6: 5 métricas — "Clientes RL6", "Crédito Otorgado", "Deuda Actual", "Morosos" (con deuda morosos como secondary), "Tasa de Cobro"
    - Tab R11: 4 métricas — "Usuarios R11", "Crédito Otorgado", "Deuda Actual", "Disponible"
    - R11 métricas calculadas agregando el array de usuarios R11 en el componente
    - RL6 métricas consumidas directamente del `summary` del API response
    - Formatear valores monetarios con `formatCLP()`
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [x] 9.2 Implementar estilos condicionales y responsive
    - Tasa de Cobro: verde >= 80%, amber 50-79%, rojo < 50%
    - Morosos: rojo con dot indicator cuando > 0, verde cuando 0
    - Mobile: condensar a 3 métricas (RL6: Deuda Actual, Morosos, Tasa de Cobro; R11: Deuda Actual, Disponible, Usuarios R11)
    - Skeleton placeholders mientras carga
    - _Requirements: 10.7, 10.8, 10.9_

  - [x] 9.3 Integrar CreditSummaryTrailing en CreditosSection
    - Pasar `<CreditSummaryTrailing>` como `trailing` en `onHeaderConfig`
    - Re-renderizar métricas tras acciones (pago manual, approve, reject)
    - _Requirements: 10.1, 10.10_

  - [ ]* 9.4 Write property test: R11 metric aggregation correctness
    - **Property 6: R11 metric aggregation correctness**
    - Generar arrays de usuarios R11 con `limite_credito_r11` y `credito_r11_usado` aleatorios
    - Verificar que las métricas calculadas (count, sum límite, sum usado, disponible) son correctas
    - Usar fast-check con vitest, mínimo 100 iteraciones
    - **Validates: Requirements 10.5**

- [x] 10. Checkpoint - Frontend completo
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Integración y wiring final
  - [x] 11.1 Verificar flujo completo de tabs en CreditosSection
    - Asegurar que cambiar entre tabs R11 y RL6 preserva data, no hace refetch innecesario
    - Asegurar que acciones en tab RL6 (approve, reject, manual-payment, send-email) actualizan la tabla y las métricas del trailing
    - Asegurar que el bulk email actualiza la columna "Último Email" para cada usuario procesado
    - _Requirements: 2.8, 8.8, 10.10_

  - [x] 11.2 Verificar flujo completo de tabs en PersonalSection
    - Asegurar que cambiar entre tabs Work y Clientes preserva data
    - Asegurar que búsqueda en Clientes funciona con debounce y muestra mensaje vacío
    - _Requirements: 5.5, 7.1, 7.2, 7.3, 7.4_

  - [ ]* 11.3 Write integration tests for email flow
    - Test: preview → send → verify email_logs entry
    - Test: bulk email → verify sequential processing and summary response
    - _Requirements: 9.1, 9.3, 9.4, 9.6, 9.7, 9.8_

- [x] 12. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Backend tasks (1-3) should be completed before frontend tasks (4-9) to have working APIs
- El patrón de tabs sigue la referencia existente en ComprasSection.tsx
- La lógica de morosidad y emails replica la existente en caja3 (get_rl6_users.php, send_dynamic_email.php, preview_email_dynamic.php)
