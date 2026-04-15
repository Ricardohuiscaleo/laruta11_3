# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-15)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`72e348c`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | 🔄 Pendiente verificar (`351753d`) |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`0992f08`) — SPA admin realtime completo |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`3d12b7a`) — AdminDataUpdatedEvent + Telegram adelantos + shift fix |
| saas-backend | admin.digitalizatodo.cl | Laravel 11 + PHP 8.4 + Reverb | ✅ Running |

### Coolify UUIDs

| App | UUID |
|-----|------|
| app3 | `egck4wwcg0ccc4osck4sw8ow` |
| caja3 | `xockcgsc8k000o8osw8o88ko` |
| landing3 | `dks4cg8s0wsswk08ocwggk0g` |
| mi3-backend | `ds24j8jlaf9ov4flk1nq4jek` |
| mi3-frontend | `sxdw43i9nt3cofrzxj28hx1e` |
| laruta11-db | `zs00occ8kcks40w4c88ogo08` |

### Scheduled Tasks

| App | Task | Frecuencia |
|-----|------|------------|
| mi3-backend | `php artisan schedule:run` (9 comandos) | `* * * * *` |
| mi3-backend | `delivery:generate-daily-settlement` | `23:59` diario |
| mi3-backend | `delivery:check-pending-settlements` | `12:00` diario |
| app3 | Gmail Token Refresh | `*/30 * * * *` |
| caja3 | Daily Checklists (legacy) | ❌ Desactivado (mi3 lo reemplaza) |

### Bot Telegram (SuperKiro)

| Componente | Estado |
|-----------|--------|
| Bot | `@SuperKiro_bot` — pm2 auto-start en VPS |
| kiro-cli | v1.29.8 en `/root/.local/bin/kiro-cli` (Builder ID) |
| Workspace | `/root/laruta11_3` ✅ verificado |
| ACP | Sesión activa, acceso completo al monorepo |
| Timeout `session/prompt` | 600s (10 min) — fix aplicado 2026-04-14 |

---

## Tareas Pendientes

### 🔴 Críticas (afectan producción)

- [x] **Actualizar `checklist_templates`** — overhaul completo con rol explícito, fotos separadas, prompts IA.
- [x] **Corregir caja3 `get_turnos.php`** — obsoleto, turnos ahora gestionados por mi3.
- [x] **Generar turnos mayo** — automático via cron `mi3:generate-shifts` (monthlyOn 25).
- [x] **Fix push subscriptions duplicadas** — reparado.
- [x] **Spec fix-sessiones**: COMPLETADO. 8 bugs auth resueltos. Sesiones sobreviven redeploys.
- [x] **Fix duplicate entry turnos** — `updateOrCreate` en ShiftController + ShiftSwapService. ✅
- [x] **Crear vista admin adelantos en mi3-frontend** — COMPLETADO. AdelantosSection con approve/reject, link en sidebar, Telegram+Push+broadcast en LoanService. Commit `6c40b02`.

### 🟡 Verificaciones pendientes

- [x] Verificar upload S3 en compras — funciona correctamente.
- [x] Verificar Gmail Token Refresh — funciona correctamente.
- [x] Verificar subida masiva agrupa ARIAKA correctamente — sistema de compras IA completamente reescrito.

### 🟢 Mejoras futuras

- [x] Obtener chat_id del grupo "Pedidos 11" — no aplica, flujo directo al bot de Telegram configurado.
- [x] **Ejecutar migraciones `checklists_v2`** — obsoleto, sistema de checklists reescrito en mi3.
- [ ] **Limpiar datos de prueba delivery** — eliminar pedidos TEST-DLV-* y SIM-* y revertir roles rider de Camila(1), Andrés(3), Dafne(18) cuando termine el testing.
- [ ] Recalcular delivery\_fee server-side en `create_order.php`
- [ ] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143)
- [x] **Verificar Google Maps en mi3-frontend** — mapId `d51ca892b68e9c5e5e2dd701` + API key funcionando ✅
- [x] **Deploy spec delivery-tracking-realtime** — commit `70650cf` pusheado. Builds disparados en Coolify. Pendiente verificar builds y ejecutar `php artisan migrate`.
- [x] **Integración caja3/app3 delivery** — webhook en caja3 y iframe en app3 implementados en commit `70650cf`.
- [ ] **Investigar arquitectura SaaS multi-tenant** — AWS Lambda + Aurora PostgreSQL + Amazon Location Service + Stripe. Dominio candidato: pocos.click (caduca 2026-12-21)

---

## Sesiones Recientes

### 2026-04-15d — Fix patrón turnos 4x4 Camila/Dafne + realtime all sections

**Cambios:**
- `config/mi3.php` + `GenerateDynamicShiftsCommand.php`: Dafne `b_id: 12→18` (Dafne Fum activa), `base_date: 2026-02-02→2026-02-01` (sincronizado con caja3).
- BD: eliminados 20 turnos duplicados del patrón viejo, regenerados 20 con patrón correcto. Abril ahora tiene bloques 4x4 limpios sin gaps.
- `AdminDataUpdatedEvent.php`: evento genérico ShouldBroadcast para todas las secciones admin.
- Broadcasts best-effort en PersonalController, ShiftController, ShiftSwapController, AdjustmentController, CreditController, PayrollController.
- `useAdminRealtime.ts`: listener `.admin.data.updated` + badges por sección.
- `AdminShell.tsx`: `refreshCounters` + key-based re-mount para auto-refresh en sección activa.

**Commits:** `0992f08`, `3d12b7a`
**Deploys:** mi3-backend ✅ (`3d12b7a`), mi3-frontend ✅ (`0992f08`)

### 2026-04-15c — SPA Admin Panel + Adelantos + Realtime (spec admin-notifications-modals)

**Cambios:**
- Refactorización completa del admin mi3 a arquitectura SPA: AdminShell con 13 SectionComponents lazy-loaded, keep-alive, URL sync via pushState/popstate.
- AdminSidebarSPA + MobileBottomNavSPA: navegación onClick sin page reload, badge indicators realtime.
- AdelantosSection: panel approve/reject con formularios inline, historial colapsable. Página `/admin/adelantos` + link en sidebar.
- NotificacionesSection: filtros por tabs (Todos/Adelantos/Cambios/Sistema), botones contextuales "Ver adelanto"/"Ver cambio".
- useAdminRealtime hook: Reverb WebSocket `private-admin.{id}` para badges en tiempo real.
- Backend: LoanRequestedEvent + AdminNotificationEvent (ShouldBroadcast), TelegramService + PushNotificationService en LoanService.solicitarPrestamo(), AdminNotificationEvent en NotificationService.crear().
- channels.php: auth `admin.{id}` channel.
- Fix ComprasSection: reemplazado iframe (causaba recursión AdminShell anidado) por componentes React directos con tabs internos + ComprasProvider.
- Realtime completo: AdminDataUpdatedEvent (ShouldBroadcast genérico) + broadcasts en PersonalController, ShiftController, ShiftSwapController, AdjustmentController, CreditController, PayrollController. useAdminRealtime escucha `.admin.data.updated` + AdminShell auto-refresh via refreshCounters key.
- Hook `qa-production` creado: smoke tests en producción via SSH (userTriggered).

**Commits:** `6c40b02`, `e4c126d`, `0992f08`
**Deploys:** mi3-backend ✅ (`0992f08`), mi3-frontend ✅ (`0992f08`)

### 2026-04-15b — Spec admin-notifications-modals + 5 hooks QA

**Cambios:**
- Spec `admin-notifications-modals` creado y expandido a SPA-like completa: requirements.md (7 reqs EARS), design.md (AdminShell + 11 SectionComponents + Reverb realtime + backend), tasks.md (7 tareas, 25 sub-tareas).
- Scope final: Refactorizar TODO el admin de mi3 a arquitectura SPA con componentes inline (sin page reload), navegación instantánea en desktop Y mobile, realtime via Reverb WebSocket, badges dinámicos, panel adelantos approve/reject, Telegram+Push en LoanService.
- 5 hooks QA creados: qa-requirements, qa-design, qa-task-list (postTaskExecution), qa-code-quality (postToolUse write), qa-pre-deploy (preToolUse shell).

**Commits:** ninguno (spec local)
**Deploys:** ninguno

### 2026-04-15a — Tab Crédito R11 en ProfileModalModern + hook inspector

**Cambios:**
- Nueva tab "R11" en ProfileModalModern de app3 para usuarios con `es_credito_r11=1` y `credito_r11_aprobado=1`.
- Muestra límite, usado, disponible, relación, historial de transacciones, botón pagar crédito, countdown al día 21.
- Branding emerald/teal para diferenciar de RL6 (amber). Banner de crédito bloqueado si aplica.
- Usa API existente `/api/r11/get_credit.php`.
- Hook `inspector-spec` creado: postTaskExecution que revisa críticamente cada tarea completada.
- Token Coolify API funcional creado (id=6, `kiro-deploy-direct`).

**Commits:** `72e348c`
**Deploys:** app3 ✅ (`72e348c`)

---

> Sesiones anteriores (155 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
