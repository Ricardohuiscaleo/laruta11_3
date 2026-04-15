# Requirements Document

## Introduction

Refactorización completa del panel admin de mi3-frontend (`/admin/*`) para convertirlo de una arquitectura multi-página (Next.js routing) a una arquitectura SPA-like basada en componentes. El sidebar permanece fijo y el contenido se renderiza como componentes React inline sin navegación de página, logrando transiciones instantáneas. Incluye nuevo componente de adelantos (loans) para aprobar/rechazar solicitudes, e integración de TelegramService + PushNotificationService en `LoanService.solicitarPrestamo()`.

## Glossary

- **AdminShell**: Layout principal del admin en vista PC — sidebar fijo a la izquierda + área de contenido a la derecha que renderiza componentes dinámicamente sin page reload
- **ActiveSection**: Estado que determina qué componente se renderiza en el área de contenido (ej: 'inicio', 'personal', 'turnos', 'nomina', etc.)
- **SectionComponent**: Componente React que encapsula la lógica de una sección admin (antes era una página Next.js separada)
- **ModalOverlay**: Componente base reutilizable para modales — bottom sheet en mobile, dialog centrado en desktop
- **LoansPanel**: Componente de gestión de adelantos con lista de pendientes, approve/reject inline, historial
- **LoanService**: Servicio backend Laravel (`App\Services\Loan\LoanService`) que gestiona la lógica de adelantos
- **TelegramService**: Servicio backend que envía mensajes al grupo Telegram de La Ruta 11
- **PushNotificationService**: Servicio backend que envía notificaciones push web a usuarios suscritos
- **Admin_Sidebar**: Menú de navegación lateral fijo que controla el ActiveSection sin navegación de página

## Requirements

### Requirement 1: SPA-like Admin Shell Architecture

**User Story:** As an admin on any device, I want the admin panel to switch sections instantly without page reloads and receive realtime updates, so that I can manage operations at maximum speed.

#### Acceptance Criteria

1. THE AdminShell SHALL render a fixed navigation (sidebar on desktop >=768px, bottom nav on mobile <768px) and a content area that switches components based on ActiveSection state without triggering Next.js page navigation on ANY screen size
2. WHEN the admin clicks a navigation link on any device, THE AdminShell SHALL update the ActiveSection state and render the corresponding SectionComponent instantly (no network request for page HTML)
3. THE AdminShell SHALL lazy-load each SectionComponent using React.lazy() + Suspense so that only the active section's code is loaded
4. THE AdminShell SHALL preserve the state of previously loaded sections when switching between them (e.g., form data, scroll position) using keep-alive pattern or state persistence
5. THE AdminShell SHALL update the browser URL using window.history.pushState() to reflect the active section (e.g., `/admin/personal`) for bookmarkability and refresh support
6. WHEN the admin refreshes the page or navigates directly to a URL like `/admin/turnos`, THE AdminShell SHALL parse the URL and set the correct ActiveSection on mount
7. THE AdminShell SHALL subscribe to Laravel Reverb WebSocket channels for realtime updates (new notifications, loan requests, shift changes) and update the active SectionComponent data without manual refresh
8. WHEN a realtime event arrives for a section that is not currently active, THE AdminShell SHALL show a badge/indicator on the corresponding navigation item

### Requirement 2: Section Components Migration

**User Story:** As a developer, I want each admin page to be extractable as a standalone component, so that the AdminShell can render them without page routing.

#### Acceptance Criteria

1. EACH existing admin page (inicio, personal, turnos, nomina, ajustes, creditos, cambios, cronjobs, delivery, notificaciones) SHALL be refactored into a SectionComponent that can be rendered both as a Next.js page (mobile) and as an inline component (desktop AdminShell)
2. EACH SectionComponent SHALL accept no required props and manage its own data fetching internally via `apiFetch`
3. THE existing Next.js page files SHALL remain as thin wrappers that import and render the corresponding SectionComponent (preserving mobile routing)
4. THE SectionComponent pattern SHALL use a shared `components/admin/sections/` directory

### Requirement 3: Loans Panel (Adelantos)

**User Story:** As an admin, I want to approve or reject salary advance requests from a dedicated adelantos panel, so that I can manage adelantos efficiently.

#### Acceptance Criteria

1. WHEN the admin selects "Adelantos" in the sidebar, THE AdminShell SHALL render the LoansPanel SectionComponent
2. THE LoansPanel SHALL fetch and display all adelanto requests from `GET /admin/loans` ordered by status priority (pendiente first)
3. THE LoansPanel SHALL display each pending adelanto as a card showing: worker name, requested amount (formatted as CLP), reason, and request date
4. WHEN the admin clicks "Aprobar" on a pending adelanto, THE LoansPanel SHALL display an inline form with an editable approved amount field (pre-filled with requested amount) and optional notes textarea
5. WHEN the admin confirms approval, THE LoansPanel SHALL send `POST /admin/loans/{id}/approve` with `monto_aprobado` and optional `notas`, then refresh the list
6. WHEN the admin clicks "Rechazar", THE LoansPanel SHALL display optional notes textarea and confirm button
7. WHEN the admin confirms rejection, THE LoansPanel SHALL send `POST /admin/loans/{id}/reject` with optional `notas`, then refresh the list
8. IF the API call returns an error, THE LoansPanel SHALL display the error message inline without losing form state
9. THE LoansPanel SHALL show loading state during API calls and disable action buttons to prevent duplicate submissions
10. THE LoansPanel SHALL display resolved adelantos (aprobado, rechazado) in a collapsible history section below pending items

### Requirement 4: Admin Sidebar Adelantos Link

**User Story:** As an admin, I want a direct "Adelantos" link in the sidebar, so that I can access loans management quickly.

#### Acceptance Criteria

1. THE Admin_Sidebar SHALL include an "Adelantos" navigation item with the Wallet icon, positioned after "Delivery"
2. WHEN clicked, THE "Adelantos" link SHALL set ActiveSection to 'adelantos' and render the LoansPanel (desktop) or navigate to `/admin/adelantos` (mobile)
3. THE "Adelantos" link SHALL show a badge indicator when there are pending adelantos awaiting approval

### Requirement 5: Telegram and Push Notifications on Loan Request

**User Story:** As an admin, I want to receive Telegram and push notifications when a worker requests a salary advance, so that I can respond promptly.

#### Acceptance Criteria

1. WHEN a worker submits a new adelanto request via `LoanService.solicitarPrestamo()`, THE LoanService SHALL send a Telegram message via `TelegramService.sendToLaruta11()` with format: "💰 Solicitud de adelanto — {nombre} — ${monto_formateado}"
2. WHEN a worker submits a new adelanto request, THE LoanService SHALL send a push notification to each active admin via `PushNotificationService.enviar()` with title "Nueva solicitud de adelanto" and URL `/admin/adelantos`
3. THE LoanService SHALL send Telegram and push notifications as best-effort operations (failures logged but do not block adelanto creation)

### Requirement 6: Notifications Section with Contextual Actions

**User Story:** As an admin, I want the notifications section to show contextual action buttons that open the relevant panel, so that I can act on notifications instantly.

#### Acceptance Criteria

1. THE Notifications SectionComponent SHALL display notifications grouped by filterable tabs (Todos, Adelantos, Cambios, Sistema)
2. WHEN a notification has `referencia_tipo` equal to "prestamo", THE notification card SHALL display a "Ver adelanto" button
3. WHEN the admin clicks "Ver adelanto", THE AdminShell SHALL switch ActiveSection to 'adelantos' with the referenced item highlighted
4. EACH notification card action button SHALL have minimum 44x44px touch target

### Requirement 7: Responsive Design

**User Story:** As an admin on any device, I want the admin panel to be fast, fluid, and fully functional with realtime updates.

#### Acceptance Criteria

1. ON mobile (<768px), THE admin panel SHALL use the AdminShell with bottom navigation and instant component switching (same SPA behavior as desktop)
2. ON desktop (>=768px), THE admin panel SHALL use the AdminShell with fixed sidebar and instant component switching
3. ALL SectionComponents SHALL be responsive and work correctly in both mobile (compact layout) and desktop (spacious layout) contexts
4. THE LoansPanel approve/reject forms SHALL stack vertically on mobile with full-width inputs and buttons
5. ALL transitions between sections SHALL complete in under 100ms (no visible loading state for cached sections)
6. REALTIME updates via Reverb SHALL work on both mobile and desktop with the same WebSocket connection
