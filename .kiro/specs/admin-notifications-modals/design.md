# Technical Design Document

## Overview

Refactorización del panel admin de mi3-frontend de arquitectura multi-página (Next.js routing) a SPA-like con componentes inline, navegación instantánea y actualizaciones realtime via Laravel Reverb WebSocket. Aplica a desktop y mobile. Incluye nuevo panel de adelantos y notificaciones Telegram/Push en LoanService.

## Architecture

### AdminShell — Single Page Admin

El layout actual (`app/admin/layout.tsx`) se convierte en un `AdminShell` client component que:

1. Mantiene `activeSection` state (string: 'inicio' | 'personal' | 'turnos' | etc.)
2. Renderiza sidebar fijo (desktop) o bottom nav (mobile) que cambia `activeSection` sin `<Link>`
3. Renderiza el `SectionComponent` correspondiente en el área de contenido
4. Sincroniza URL via `window.history.pushState` / `popstate` listener
5. Conecta a Reverb WebSocket para realtime updates

```
┌─────────────────────────────────────────────┐
│ AdminShell (client component)               │
│ ┌──────────┬──────────────────────────────┐ │
│ │ Sidebar  │  Content Area               │ │
│ │ (fixed)  │  ┌────────────────────────┐  │ │
│ │          │  │ SectionComponent       │  │ │
│ │ onClick→ │  │ (lazy loaded, cached)  │  │ │
│ │ setState │  │                        │  │ │
│ │          │  └────────────────────────┘  │ │
│ └──────────┴──────────────────────────────┘ │
│ WebSocket (Reverb) ←→ badges + data refresh │
└─────────────────────────────────────────────┘
```

### Section Components

Cada página admin actual se extrae como componente standalone en `components/admin/sections/`:

| Section | Component | Source Page |
|---------|-----------|-------------|
| inicio | `DashboardSection.tsx` | `app/admin/page.tsx` |
| personal | `PersonalSection.tsx` | `app/admin/personal/page.tsx` |
| turnos | `TurnosSection.tsx` | `app/admin/turnos/page.tsx` |
| nomina | `NominaSection.tsx` | `app/admin/nomina/page.tsx` |
| ajustes | `AjustesSection.tsx` | `app/admin/ajustes/page.tsx` |
| creditos | `CreditosSection.tsx` | `app/admin/creditos/page.tsx` |
| cambios | `CambiosSection.tsx` | `app/admin/cambios/page.tsx` |
| cronjobs | `CronjobsSection.tsx` | `app/admin/cronjobs/page.tsx` |
| delivery | `DeliverySection.tsx` | `app/admin/delivery/page.tsx` |
| notificaciones | `NotificacionesSection.tsx` | `app/admin/notificaciones/page.tsx` |
| adelantos | `AdelantosSection.tsx` | NEW |

### Lazy Loading + Caching Strategy

```typescript
const sections: Record<string, React.LazyExoticComponent<any>> = {
  inicio: lazy(() => import('@/components/admin/sections/DashboardSection')),
  personal: lazy(() => import('@/components/admin/sections/PersonalSection')),
  // ... etc
};

// Keep-alive: render all loaded sections, hide inactive ones with CSS
{Object.entries(loadedSections).map(([key, Component]) => (
  <div key={key} className={activeSection === key ? 'block' : 'hidden'}>
    <Suspense fallback={<SectionSkeleton />}>
      <Component />
    </Suspense>
  </div>
))}
```

### Realtime via Reverb

```typescript
// Hook: useAdminRealtime()
// Connects to private-admin.{adminId} channel
// Events:
//   - AdminNotification → increment notification badge, refresh if active
//   - LoanRequested → increment adelantos badge, refresh if active
//   - ShiftSwapRequested → increment cambios badge
```

Backend ya tiene Reverb configurado (delivery tracking). Se agregan 2 nuevos eventos:
- `AdminNotificationEvent` — broadcast cuando se crea notificación para admin
- `LoanRequestedEvent` — broadcast cuando worker solicita adelanto

### URL Sync

```typescript
// On section change:
window.history.pushState({ section }, '', `/admin/${section === 'inicio' ? '' : section}`);

// On mount (parse URL):
const path = window.location.pathname.replace('/admin/', '').replace('/admin', '') || 'inicio';
setActiveSection(path);

// On popstate (browser back/forward):
window.addEventListener('popstate', (e) => {
  setActiveSection(e.state?.section || 'inicio');
});
```

## Components

### 1. AdminShell (`components/admin/AdminShell.tsx`)

```typescript
interface AdminShellProps {
  initialSection?: string; // from URL on SSR
}
```

- Client component ('use client')
- State: `activeSection`, `loadedSections` (Set), `badges` (Record<string, number>)
- Renders: AdminSidebarSPA (desktop) + MobileBottomNavSPA (mobile) + content area
- Connects useAdminRealtime() hook
- URL sync via pushState/popstate

### 2. AdminSidebarSPA (`components/admin/AdminSidebarSPA.tsx`)

- Same visual as current AdminSidebar but uses `onClick` + `setActiveSection` instead of `<Link>`
- Receives `activeSection`, `onSectionChange`, `badges` as props
- Shows badge indicators for sections with pending realtime updates

### 3. MobileBottomNavSPA (`components/admin/MobileBottomNavSPA.tsx`)

- Same visual as current MobileBottomNav admin variant but uses `onClick` + `setActiveSection`
- Primary items in bottom bar, secondary in sheet menu
- Badge indicators same as sidebar

### 4. AdelantosSection (`components/admin/sections/AdelantosSection.tsx`)

- Fetches `GET /admin/loans` on mount
- Pending adelantos as cards: nombre, monto (formatCLP), motivo, fecha
- Approve: inline form with monto_aprobado (pre-filled) + notas textarea
- Reject: inline notas textarea + confirm
- Loading/disabled state during API calls
- Error display inline (bg-red-50)
- Collapsible history section for resolved items
- Listens to `LoanRequested` realtime event to auto-refresh

### 5. NotificacionesSection (refactored)

- Filter tabs: Todos | Adelantos | Cambios | Sistema
- Notification cards with contextual action buttons
- "Ver adelanto" button calls `onNavigate('adelantos', { highlightId })` prop
- Auto-marks as read on mount
- Listens to `AdminNotification` realtime event

### 6. useAdminRealtime Hook

```typescript
function useAdminRealtime(adminId: number): {
  badges: Record<string, number>;
  clearBadge: (section: string) => void;
  onEvent: (callback: (event: RealtimeEvent) => void) => void;
}
```

- Connects to `private-admin.{adminId}` Reverb channel via Echo
- Tracks badge counts per section
- Exposes event callback for section-specific refresh

### 7. Backend Changes

**LoanService.php** — Add TelegramService + PushNotificationService + broadcast:

```php
public function __construct(
    private NotificationService $notificationService,
    private TelegramService $telegramService,  // NEW
) {}

// In solicitarPrestamo(), after admin notifications:
try {
    $this->telegramService->sendToLaruta11(
        "💰 Solicitud de adelanto — {$personal->nombre} — \$" 
        . number_format($monto, 0, ',', '.')
    );
} catch (\Throwable $e) {
    Log::warning('Telegram adelanto: ' . $e->getMessage());
}

foreach ($admins as $admin) {
    try {
        app(PushNotificationService::class)->enviar(
            $admin->id,
            '💰 Nueva solicitud de adelanto',
            "{$personal->nombre} solicita \$" . number_format($monto, 0, ',', '.'),
            '/admin/adelantos'
        );
    } catch (\Throwable $e) { /* best-effort */ }
}

// Broadcast realtime event
broadcast(new LoanRequestedEvent($prestamo))->toOthers();
```

**New Events:**
- `LoanRequestedEvent` — broadcasts on `private-admin.{adminId}` for each admin
- `AdminNotificationEvent` — broadcasts when NotificationService creates admin notification

## Correctness Properties

### Property 1: Section Switching Preserves State
- For any sequence of section switches A→B→A, the state of section A (form data, scroll position, loaded data) is preserved when returning to it.

### Property 2: URL Sync Bidirectional
- For any activeSection value, pushState produces a URL that, when parsed on mount, produces the same activeSection value. And vice versa.

### Property 3: Telegram Best-Effort Independence
- For any valid loan request, if TelegramService.sendToLaruta11() throws any exception, the Prestamo record is still created successfully and push notifications to other admins are still attempted.

### Property 4: Realtime Badge Accuracy
- For any realtime event received, the badge count for the corresponding section increments by exactly 1. When the admin switches to that section, the badge resets to 0.

## File Changes

### New Files
| File | Purpose |
|------|---------|
| `mi3/frontend/components/admin/AdminShell.tsx` | SPA shell with section switching, URL sync, realtime |
| `mi3/frontend/components/admin/AdminSidebarSPA.tsx` | Sidebar with onClick navigation + badges |
| `mi3/frontend/components/admin/MobileBottomNavSPA.tsx` | Mobile bottom nav with onClick + badges |
| `mi3/frontend/components/admin/sections/DashboardSection.tsx` | Extracted from admin/page.tsx |
| `mi3/frontend/components/admin/sections/PersonalSection.tsx` | Extracted from admin/personal/page.tsx |
| `mi3/frontend/components/admin/sections/TurnosSection.tsx` | Extracted from admin/turnos/page.tsx |
| `mi3/frontend/components/admin/sections/NominaSection.tsx` | Extracted from admin/nomina/page.tsx |
| `mi3/frontend/components/admin/sections/AjustesSection.tsx` | Extracted from admin/ajustes/page.tsx |
| `mi3/frontend/components/admin/sections/CreditosSection.tsx` | Extracted from admin/creditos/page.tsx |
| `mi3/frontend/components/admin/sections/CambiosSection.tsx` | Extracted from admin/cambios/page.tsx |
| `mi3/frontend/components/admin/sections/CronjobsSection.tsx` | Extracted from admin/cronjobs/page.tsx |
| `mi3/frontend/components/admin/sections/DeliverySection.tsx` | Extracted from admin/delivery/page.tsx |
| `mi3/frontend/components/admin/sections/NotificacionesSection.tsx` | Refactored with filters + contextual actions |
| `mi3/frontend/components/admin/sections/AdelantosSection.tsx` | NEW: loans approve/reject panel |
| `mi3/frontend/hooks/useAdminRealtime.ts` | Reverb WebSocket hook for admin realtime |
| `mi3/backend/app/Events/LoanRequestedEvent.php` | Broadcast event for loan requests |
| `mi3/backend/app/Events/AdminNotificationEvent.php` | Broadcast event for admin notifications |

### Modified Files
| File | Change |
|------|--------|
| `mi3/frontend/app/admin/layout.tsx` | Replace with AdminShell |
| `mi3/frontend/app/admin/page.tsx` | Thin wrapper importing DashboardSection |
| `mi3/frontend/app/admin/*/page.tsx` | Thin wrappers importing SectionComponents |
| `mi3/frontend/lib/navigation.ts` | Add "Adelantos" to admin nav items |
| `mi3/backend/app/Services/Loan/LoanService.php` | Add Telegram + Push + broadcast |
| `mi3/backend/routes/channels.php` | Add private-admin.{id} channel auth |
