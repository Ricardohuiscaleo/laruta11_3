# Requirements Document

## Introduction

Esta feature reorganiza dos secciones del panel admin de mi3-frontend (mi.laruta11.cl/admin): la página de Créditos y la página de Personal/Usuarios. Actualmente, la sección "Créditos R11" solo muestra créditos R11 (Crédito Ruta 11). Se necesita agregar un sistema de tabs para mostrar también los créditos RL6 (Regimiento Logístico N°6) en la misma página, renombrando el ítem del sidebar a "Créditos". Paralelamente, la sección "Personal" se renombra a "Usuarios" y se le agregan tabs para separar trabajadores internos ("Ruta 11 Work") de clientes de la app ("Clientes"), tomando como referencia el panel admin de caja3 y mejorándolo.

## Glossary

- **Admin_Panel**: Panel de administración en mi3-frontend (mi.laruta11.cl/admin), construido con Next.js 14 + React, usando SPA routing via AdminShell.tsx
- **AdminShell**: Componente principal que gestiona secciones lazy-loaded, tabs unificados (UnifiedHeader), y navegación SPA con pushState
- **SectionHeaderConfig**: Interfaz que las secciones usan para registrar tabs, trailing content y accent color en el header unificado via `onHeaderConfig`
- **CreditosSection**: Componente React en `components/admin/sections/CreditosSection.tsx` que muestra la gestión de créditos
- **PersonalSection**: Componente React en `components/admin/sections/PersonalSection.tsx` que muestra la gestión de personal/trabajadores
- **AdminSidebarSPA**: Componente de navegación lateral desktop con links a secciones
- **MobileBottomNavSPA**: Componente de navegación inferior mobile con items primarios y sheet secundario
- **Crédito_R11**: Sistema de crédito interno "Crédito Ruta 11" para personas relacionadas con el negocio. Datos en campos `es_credito_r11`, `credito_r11_*` de tabla `usuarios`
- **Crédito_RL6**: Sistema de crédito exclusivo para militares del Regimiento Logístico N°6 con pago mensual día 21. Datos en campos `es_militar_rl6`, `credito_*` de tabla `usuarios`, transacciones en `rl6_credit_transactions`
- **Admin_CreditController**: Controlador Laravel en mi3-backend que actualmente gestiona solo créditos R11 via `/admin/credits`
- **Tabla_Usuarios**: Tabla `usuarios` de MySQL compartida entre app3 y caja3, contiene tanto clientes como militares RL6 y usuarios R11
- **Tabla_Personal**: Tabla `personal` de mi3-backend para trabajadores internos de Ruta 11 (cajeros, plancheros, admin, seguridad)
- **Caja3_Users_API**: Endpoints PHP en `caja3/api/users/` que listan usuarios de la tabla `usuarios` con estadísticas de órdenes
- **Moroso**: Usuario RL6 con deuda vencida. Condición: hoy > día 21 del mes AND tiene débitos del ciclo vencido (día 22 mes anterior → día 21 mes actual) AND no ha pagado este mes. Lógica existente en `caja3/api/gmail/get_rl6_users.php`
- **Ciclo_Vencido**: Período de facturación RL6 que va desde el día 22 del mes anterior hasta el día 21 del mes actual (inclusive). Los débitos dentro de este rango constituyen la deuda exigible
- **Días_Mora**: Cantidad de días transcurridos desde el día 21 del mes actual. Se calcula como `día_actual - 21` cuando el usuario es moroso
- **Email_Estado**: Clasificación del email de cobranza según el estado crediticio del usuario. Cuatro tipos: `sin_deuda` (verde), `recordatorio` (naranja), `urgente` (rojo-naranja, días 18-21), `moroso` (rojo oscuro, día 22+)
- **Email_Logs**: Tabla `email_logs` que registra todos los emails enviados con campos: user_id, email_to, email_type, subject, gmail_message_id, status, sent_at
- **Gmail_Tokens**: Tabla `gmail_tokens` que almacena tokens OAuth de Gmail con auto-refresh para el envío de emails via Gmail API
- **Send_Dynamic_Email**: Endpoint PHP en `caja3/api/gmail/send_dynamic_email.php` que envía emails de cobranza personalizados según el estado crediticio del usuario
- **Preview_Email_Dynamic**: Función PHP en `caja3/api/gmail/preview_email_dynamic.php` que genera el HTML del email de cobranza con diseño responsive y colores según Email_Estado
- **CreditSummaryTrailing**: Componente React que se renderiza en el área `trailing` del SectionHeaderConfig de CreditosSection, mostrando métricas financieras contextuales al tab activo (RL6 o R11) en una franja horizontal tipo dashboard-strip en el header al lado del título "Créditos"
- **Summary_Totals**: Objeto de datos agregados que el endpoint GET `/admin/credits/rl6` retorna junto con la lista de usuarios, conteniendo totales precalculados del servidor para evitar cálculos en el frontend
- **Tasa_Cobro**: Porcentaje de usuarios con deuda que han pagado en el mes actual. Fórmula: (usuarios_pagados_este_mes / total_usuarios_con_deuda) × 100

## Requirements

### Requirement 1: Renombrar sidebar "Créditos R11" a "Créditos"

**User Story:** Como administrador, quiero que el menú lateral muestre "Créditos" en vez de "Créditos R11", para que refleje que la sección ahora contiene ambos tipos de crédito.

#### Acceptance Criteria

1. THE Admin_Panel SHALL display "Créditos" as the sidebar label for the credits section in AdminSidebarSPA
2. THE Admin_Panel SHALL display "Créditos" as the label in MobileBottomNavSPA for the credits section
3. THE AdminShell SHALL display "Créditos" as the section title in SECTION_TITLES for the `creditos` key

### Requirement 2: Sistema de tabs en página de Créditos

**User Story:** Como administrador, quiero ver tabs "Créditos R11" y "Créditos RL6" en la página de créditos, para gestionar ambos tipos de crédito desde un solo lugar.

#### Acceptance Criteria

1. WHEN the CreditosSection loads, THE CreditosSection SHALL register two tabs via onHeaderConfig: "Créditos R11" (key `r11`) and "Créditos RL6" (key `rl6`)
2. WHEN the tab "Créditos R11" is active, THE CreditosSection SHALL display the existing R11 credit management table with columns: Nombre, Límite, Usado, Disponible, Estado, Acciones
3. WHEN the tab "Créditos RL6" is active, THE CreditosSection SHALL display a table of RL6 credit users with columns: Nombre, RUT, Grado, Límite, Usado, Disponible, Estado Mora, Días Mora, Último Email, Acciones
4. WHEN a user is Moroso, THE CreditosSection SHALL display a red badge "Moroso" in the Estado Mora column with the Días_Mora count visible in the Días Mora column
5. WHEN a user is not Moroso and has debt, THE CreditosSection SHALL display an orange badge "Con deuda" in the Estado Mora column
6. WHEN a user has no debt, THE CreditosSection SHALL display a green badge "Al día" in the Estado Mora column
7. THE CreditosSection SHALL default to the "Créditos R11" tab on initial load
8. WHEN switching between tabs, THE CreditosSection SHALL preserve loaded data for each tab to avoid unnecessary API refetches

### Requirement 3: API endpoint para créditos RL6 en admin

**User Story:** Como administrador, quiero un endpoint API que liste los usuarios con crédito RL6 y permita gestionar aprobaciones, rechazos y pagos manuales, para administrar el sistema de crédito militar.

#### Acceptance Criteria

1. THE Admin_CreditController SHALL expose a GET endpoint `/admin/credits/rl6` that returns a response with two top-level keys: `data` (array of user objects) and `summary` (Summary_Totals object), where `data` contains all users where `es_militar_rl6 = 1` AND `credito_aprobado = 1` with fields: id, nombre, email, telefono, rut, grado_militar, unidad_trabajo, limite_credito, credito_usado, disponible, credito_aprobado, credito_bloqueado, fecha_ultimo_pago, es_moroso, dias_mora, deuda_ciclo_vencido, pagado_este_mes, ultimo_email_enviado, ultimo_email_tipo
2. THE Admin_CreditController SHALL return a `summary` object in the GET `/admin/credits/rl6` response containing: `total_usuarios` (count of active RL6 credit users), `total_credito_otorgado` (sum of all limite_credito), `total_deuda_actual` (sum of all credito_usado), `total_morosos` (count of Moroso users), `total_deuda_morosos` (sum of credito_usado for Moroso users), `pagos_del_mes_count` (count of users with refund transactions in rl6_credit_transactions in the current calendar month), `pagos_del_mes_monto` (sum of refund amounts in rl6_credit_transactions in the current calendar month), `tasa_cobro` (percentage: pagos_del_mes_count divided by count of users with credito_usado greater than 0, multiplied by 100, rounded to one decimal)
3. THE Admin_CreditController SHALL expose a POST endpoint `/admin/credits/rl6/{id}/approve` that sets `credito_aprobado = 1` and assigns `limite_credito` from the request payload
4. THE Admin_CreditController SHALL expose a POST endpoint `/admin/credits/rl6/{id}/reject` that sets `credito_aprobado = 0` and `limite_credito = 0`
5. THE Admin_CreditController SHALL expose a POST endpoint `/admin/credits/rl6/{id}/manual-payment` that creates a `refund` entry in `rl6_credit_transactions`, reduces `credito_usado`, and updates `fecha_ultimo_pago`
6. IF the manual payment reduces `credito_usado` to 0 and the user is blocked, THEN THE Admin_CreditController SHALL set `credito_bloqueado = 0` to unblock the user
7. THE Admin_CreditController SHALL calculate `es_moroso` using the Moroso logic: today > day 21 of current month AND user has debit transactions in the Ciclo_Vencido (day 22 previous month → day 21 current month) AND `fecha_ultimo_pago` is not in the current month
8. THE Admin_CreditController SHALL calculate `dias_mora` as the number of days since day 21 of the current month when the user is Moroso, and 0 otherwise
9. THE Admin_CreditController SHALL return `ultimo_email_enviado` as the most recent `sent_at` from the Email_Logs table for each user, and `ultimo_email_tipo` as the corresponding `email_type`

### Requirement 4: Renombrar sidebar "Personal" a "Usuarios"

**User Story:** Como administrador, quiero que el menú lateral muestre "Usuarios" en vez de "Personal", para que refleje que la sección ahora contiene tanto trabajadores como clientes.

#### Acceptance Criteria

1. THE Admin_Panel SHALL display "Usuarios" as the sidebar label for the personal section in AdminSidebarSPA
2. THE Admin_Panel SHALL display "Usuarios" as the label in MobileBottomNavSPA for the personal section
3. THE AdminShell SHALL display "Usuarios" as the section title in SECTION_TITLES for the `personal` key

### Requirement 5: Sistema de tabs en página de Usuarios

**User Story:** Como administrador, quiero ver tabs "Ruta 11 Work" y "Clientes" en la página de usuarios, para gestionar trabajadores internos y clientes de la app desde un solo lugar.

#### Acceptance Criteria

1. WHEN the PersonalSection loads, THE PersonalSection SHALL register two tabs via onHeaderConfig: "Ruta 11 Work" (key `work`) and "Clientes" (key `clientes`)
2. WHEN the tab "Ruta 11 Work" is active, THE PersonalSection SHALL display the existing personal/workers management table with columns: Nombre, Roles, Sueldos, Estado, Acciones
3. WHEN the tab "Clientes" is active, THE PersonalSection SHALL display a table of app3 customer users from the `usuarios` table with columns: Nombre, Email, Teléfono, Pedidos, Total Gastado, Último Pedido, Estado
4. THE PersonalSection SHALL default to the "Ruta 11 Work" tab on initial load
5. WHEN switching between tabs, THE PersonalSection SHALL preserve loaded data for each tab to avoid unnecessary API refetches

### Requirement 6: API endpoint para clientes (usuarios app3) en admin

**User Story:** Como administrador, quiero un endpoint API que liste los clientes de la app con sus estadísticas de compra, para poder ver y gestionar los usuarios de app3 desde mi3.

#### Acceptance Criteria

1. THE Admin_CreditController SHALL expose a GET endpoint `/admin/users/customers` that returns all users from the `usuarios` table with fields: id, nombre, email, telefono, fecha_registro, activo, total_orders, total_spent, last_order_date
2. THE endpoint SHALL calculate total_orders and total_spent from `tuu_orders` where `payment_status = 'paid'`, joined by `user_id`
3. THE endpoint SHALL support an optional query parameter `search` that filters by nombre or email using LIKE matching
4. THE endpoint SHALL order results by `id DESC` (newest first)

### Requirement 7: Funcionalidad de búsqueda en tab Clientes

**User Story:** Como administrador, quiero poder buscar clientes por nombre o email, para encontrar rápidamente un usuario específico.

#### Acceptance Criteria

1. WHEN the tab "Clientes" is active, THE PersonalSection SHALL display a search input field above the table
2. WHEN the administrator types in the search field, THE PersonalSection SHALL debounce the input by 300ms before sending the API request with the search parameter
3. WHEN search results return, THE PersonalSection SHALL display only the matching users in the table
4. IF no users match the search, THEN THE PersonalSection SHALL display a message "No se encontraron clientes"

### Requirement 8: Botones de acción rápida para cobranza de morosos en tab RL6

**User Story:** Como administrador, quiero poder enviar emails de cobranza a usuarios morosos directamente desde el tab Créditos RL6, para agilizar la gestión de cobros sin salir del panel admin.

#### Acceptance Criteria

1. WHEN the tab "Créditos RL6" is active, THE CreditosSection SHALL display a button "Enviar Email" in the Acciones column for each user with debt (credito_usado > 0)
2. WHEN the administrator clicks "Enviar Email" for a specific user, THE CreditosSection SHALL call the email preview API and display a modal with the email HTML preview, the detected Email_Estado type, and the recipient email address
3. WHEN the administrator confirms sending in the preview modal, THE CreditosSection SHALL call the send email API for that user and display a success or error notification
4. WHEN the tab "Créditos RL6" is active and at least one user is Moroso, THE CreditosSection SHALL display a bulk action button "Cobrar a Morosos" above the table
5. WHEN the administrator clicks "Cobrar a Morosos", THE CreditosSection SHALL display a confirmation dialog showing the count of Moroso users and the action to be taken
6. WHEN the administrator confirms the bulk action, THE CreditosSection SHALL send collection emails to all Moroso users sequentially and display a progress indicator with success/failure count
7. IF an email fails to send during bulk action, THEN THE CreditosSection SHALL continue sending to remaining users and display the failed user names in the final summary
8. WHEN an email is sent successfully, THE CreditosSection SHALL update the "Último Email" column for that user without requiring a full page refresh

### Requirement 9: API endpoints para envío de emails de cobranza desde mi3-backend

**User Story:** Como administrador, quiero endpoints API en mi3-backend que permitan previsualizar y enviar emails de cobranza RL6, para integrar la funcionalidad de emails existente en caja3 con el panel admin de mi3.

#### Acceptance Criteria

1. THE Admin_CreditController SHALL expose a GET endpoint `/admin/credits/rl6/{id}/preview-email` that returns the email HTML preview and the detected Email_Estado type for the specified user
2. THE Admin_CreditController SHALL calculate the Email_Estado for preview using the same logic as Send_Dynamic_Email: `sin_deuda` when credito_usado is 0, `recordatorio` when day <= 20 or user paid this month, `urgente` on day 21, `moroso` after day 21 with unpaid Ciclo_Vencido debt
3. THE Admin_CreditController SHALL expose a POST endpoint `/admin/credits/rl6/{id}/send-email` that sends a collection email to the specified user and logs the result in Email_Logs
4. THE Admin_CreditController SHALL expose a POST endpoint `/admin/credits/rl6/send-bulk-emails` that accepts an array of user IDs and sends collection emails to each user sequentially
5. WHEN sending an email, THE Admin_CreditController SHALL proxy the request to caja3's Send_Dynamic_Email endpoint or replicate its logic: build the email HTML via Preview_Email_Dynamic, send via Gmail API using Gmail_Tokens, and log in Email_Logs
6. WHEN an email is sent successfully, THE Admin_CreditController SHALL insert a record in Email_Logs with email_type set to the detected Email_Estado, the gmail_message_id, and status 'sent'
7. IF the Gmail API returns an error, THEN THE Admin_CreditController SHALL insert a record in Email_Logs with status 'failed' and the error_message, and return the error to the client
8. THE Admin_CreditController SHALL return a summary response for bulk email operations containing: total_sent, total_failed, and an array of failed user details with error messages

### Requirement 10: Franja de métricas contextuales en header de Créditos

**User Story:** Como administrador, quiero ver métricas financieras clave en el header de la sección Créditos que cambien según el tab activo (RL6 o R11), para tener visibilidad inmediata del estado de cada sistema de crédito sin revisar la tabla completa.

#### Acceptance Criteria

1. WHEN the CreditosSection loads, THE CreditosSection SHALL register a CreditSummaryTrailing component in the `trailing` field of SectionHeaderConfig, rendering a horizontal dashboard-strip in the header space to the right of the title "Créditos"
2. WHEN the tab "Créditos RL6" is active, THE CreditSummaryTrailing SHALL display exactly five metrics in a horizontal row: "Clientes RL6" (total_usuarios from Summary_Totals), "Crédito Otorgado" (total_credito_otorgado formatted as CLP), "Deuda Actual" (total_deuda_actual formatted as CLP), "Morosos" (total_morosos count with total_deuda_morosos formatted as CLP as secondary text), and "Tasa de Cobro" (tasa_cobro as percentage)
3. WHEN the tab "Créditos R11" is active, THE CreditSummaryTrailing SHALL display exactly four metrics in a horizontal row: "Usuarios R11" (count of R11 credit users from the R11 tab data), "Crédito Otorgado" (sum of limite_credito_r11 from R11 tab data), "Deuda Actual" (sum of credito_r11_usado from R11 tab data), and "Disponible" (total crédito otorgado minus total deuda actual)
4. THE CreditSummaryTrailing SHALL consume RL6 metrics from the `summary` field of the GET `/admin/credits/rl6` API response, without making additional API calls
5. THE CreditSummaryTrailing SHALL compute R11 metrics by aggregating the user array from the existing R11 tab API response, without making additional API calls
6. THE CreditSummaryTrailing SHALL format all monetary values in CLP with dot separators using the existing formatCLP utility function
7. THE CreditSummaryTrailing SHALL apply color-coded styling to metrics: green text for "Tasa de Cobro" when tasa_cobro is 80 or above, amber text when between 50 and 79, red text when below 50; red text and red dot indicator for "Morosos" when total_morosos is greater than 0, green text when total_morosos is 0
8. WHEN the viewport is mobile width, THE CreditSummaryTrailing SHALL display a condensed version showing only three metrics: for RL6 tab show "Deuda Actual", "Morosos", and "Tasa de Cobro"; for R11 tab show "Deuda Actual", "Disponible", and "Usuarios R11"
9. WHILE the credit data is still loading, THE CreditSummaryTrailing SHALL display skeleton placeholders for each metric to indicate loading state
10. WHEN the data for the active tab finishes loading or updates after an action (manual payment, approve, reject), THE CreditSummaryTrailing SHALL re-render the metrics to reflect the latest data
