# Implementation Tasks

## Task 1: Extract existing admin pages into SectionComponents

- [x] 1.1 Create `mi3/frontend/components/admin/sections/DashboardSection.tsx` — move all logic from `app/admin/page.tsx` into standalone component (no props, self-contained data fetching)
- [x] 1.2 Create `mi3/frontend/components/admin/sections/PersonalSection.tsx` — move all logic from `app/admin/personal/page.tsx`
- [x] 1.3 Create `mi3/frontend/components/admin/sections/TurnosSection.tsx` — move all logic from `app/admin/turnos/page.tsx`
- [x] 1.4 Create `mi3/frontend/components/admin/sections/NominaSection.tsx` — move all logic from `app/admin/nomina/page.tsx`
- [x] 1.5 Create `mi3/frontend/components/admin/sections/AjustesSection.tsx` — move all logic from `app/admin/ajustes/page.tsx`
- [x] 1.6 Create `mi3/frontend/components/admin/sections/CreditosSection.tsx` — move all logic from `app/admin/creditos/page.tsx`
- [x] 1.7 Create `mi3/frontend/components/admin/sections/CambiosSection.tsx` — move all logic from `app/admin/cambios/page.tsx`
- [x] 1.8 Create `mi3/frontend/components/admin/sections/CronjobsSection.tsx` — move all logic from `app/admin/cronjobs/page.tsx`
- [x] 1.9 Create `mi3/frontend/components/admin/sections/DeliverySection.tsx` — move all logic from `app/admin/delivery/page.tsx`
- [x] 1.10 Convert each `app/admin/*/page.tsx` into thin wrapper that imports and renders the corresponding SectionComponent (preserves Next.js routing for direct URL access and SEO)

## Task 2: Create AdelantosSection + NotificacionesSection

- [x] 2.1 Create `mi3/frontend/components/admin/sections/AdelantosSection.tsx` — fetch GET /admin/loans, display pending cards (nombre, monto formatCLP, motivo, fecha), inline approve form (monto_aprobado pre-filled + notas), inline reject form (notas + confirm), loading/disabled state, error inline, collapsible history
- [x] 2.2 Refactor `mi3/frontend/components/admin/sections/NotificacionesSection.tsx` — add filter tabs (Todos, Adelantos, Cambios, Sistema), notification cards with contextual action buttons ("Ver adelanto" for prestamo, "Ver cambio" for cambio_turno), onNavigate callback to switch section
- [x] 2.3 Add `{ href: '/admin/adelantos', label: 'Adelantos', icon: Wallet }` to admin nav items in `mi3/frontend/lib/navigation.ts`

## Task 3: Create AdminShell SPA component

- [x] 3.1 Create `mi3/frontend/components/admin/AdminShell.tsx` — client component with activeSection state, lazy-loaded section registry, keep-alive rendering (hidden/block CSS), URL sync via pushState/popstate, Suspense fallback skeleton, onNavigate function for cross-section navigation
- [x] 3.2 Create `mi3/frontend/components/admin/AdminSidebarSPA.tsx` — same visual as AdminSidebar but onClick + setActiveSection instead of Link, receives activeSection/onSectionChange/badges props, badge indicators per section
- [x] 3.3 Create `mi3/frontend/components/admin/MobileBottomNavSPA.tsx` — same visual as MobileBottomNav admin variant but onClick + setActiveSection, primary items in bottom bar, secondary in expandable sheet, badge indicators
- [x] 3.4 Update `mi3/frontend/app/admin/layout.tsx` — replace current layout with AdminShell, pass initialSection from URL pathname

## Task 4: Realtime via Reverb WebSocket

- [x] 4.1 Create `mi3/frontend/hooks/useAdminRealtime.ts` — connect to private-admin.{adminId} Reverb channel via Echo, track badge counts per section, expose clearBadge(section) and onEvent callback, auto-reconnect on disconnect
- [x] 4.2 Create `mi3/backend/app/Events/LoanRequestedEvent.php` — implements ShouldBroadcast, broadcasts on private-admin.{adminId} for each active admin, payload: prestamo data + personal name
- [x] 4.3 Create `mi3/backend/app/Events/AdminNotificationEvent.php` — implements ShouldBroadcast, broadcasts on private-admin.{adminId}, payload: notification data
- [x] 4.4 Update `mi3/backend/routes/channels.php` — add auth for `private-admin.{id}` channel (verify personal is admin)
- [x] 4.5 Integrate useAdminRealtime in AdminShell — pass badges to sidebar/bottom nav, auto-refresh active section on relevant events, clear badge when section becomes active

## Task 5: Backend — Telegram + Push + Broadcast in LoanService

- [x] 5.1 Modify `mi3/backend/app/Services/Loan/LoanService.php` — inject TelegramService in constructor, add sendToLaruta11() call after admin notifications with format "💰 Solicitud de adelanto — {nombre} — ${monto}", add PushNotificationService.enviar() to each admin with URL /admin/adelantos, add broadcast(new LoanRequestedEvent($prestamo)), all as best-effort try/catch with Log::warning
- [x] 5.2 Update `mi3/backend/app/Services/Notification/NotificationService.php` — add broadcast(new AdminNotificationEvent()) when creating notification for admin users

## Task 6: Testing

- [ ] 6.1 Test AdminShell section switching — verify all 11 sections load correctly, URL updates, back/forward navigation works, state preserved on switch
- [ ] 6.2 Test AdelantosSection — verify approve/reject flow, error handling, list refresh, loading states
- [ ] 6.3 Test realtime — verify badges update on LoanRequested event, auto-refresh on active section, badge clears on section switch
- [ ] 6.4 Test mobile — verify bottom nav SPA behavior, responsive layouts, touch targets >=44px

## Task 7: Deploy

- [x] 7.1 Commit all changes, push to main
- [x] 7.2 Deploy mi3-backend via Coolify API (UUID: ds24j8jlaf9ov4flk1nq4jek) — token: `6|kiro-deploy-secret-2026`
- [x] 7.3 Deploy mi3-frontend via Coolify API (UUID: sxdw43i9nt3cofrzxj28hx1e)
- [x] 7.4 Verify both deploys finished successfully
- [x] 7.5 Smoke test: open mi.laruta11.cl/admin, verify SPA navigation, verify realtime badges
