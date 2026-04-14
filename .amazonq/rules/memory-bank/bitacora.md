# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-14)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`913b5ec`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`913b5ec`) |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`246848b`) |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`246848b`) |
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

### 🟡 Verificaciones pendientes

- [x] Verificar upload S3 en compras — funciona correctamente.
- [x] Verificar Gmail Token Refresh — funciona correctamente.
- [x] Verificar subida masiva agrupa ARIAKA correctamente — sistema de compras IA completamente reescrito.

### 🟢 Mejoras futuras

- [x] Obtener chat_id del grupo "Pedidos 11" — no aplica, flujo directo al bot de Telegram configurado.
- [x] **Ejecutar migraciones `checklists_v2`** — obsoleto, sistema de checklists reescrito en mi3.
- [ ] Recalcular delivery\_fee server-side en `create_order.php`
- [ ] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143)
- [ ] **Deploy spec delivery-tracking-realtime** — hacer commit + deploy mi3-backend, mi3-frontend, app3 y caja3. Requiere `php artisan migrate` en mi3-backend. Google Maps key ya configurada en Coolify.
- [ ] **Integración caja3/app3 delivery** — webhook order-status en caja3, iframe Vista Cliente en app3 (fase posterior del spec)
- [ ] **Investigar arquitectura SaaS multi-tenant** — AWS Lambda + Aurora PostgreSQL + Amazon Location Service + Stripe. Dominio candidato: pocos.click (caduca 2026-12-21)

---

## Sesiones Recientes

### 2026-04-14j — Spec delivery-tracking-realtime: implementación completa (sin deploy)

**Cambios:**
- **mi3-backend**: 4 migraciones (rider_locations, delivery_assignments, daily_settlements, campos en tuu_orders), 3 modelos Eloquent, 2 eventos Reverb (RiderLocationUpdated, OrderStatusUpdated), routes/channels.php, 3 servicios (DeliveryService, LocationService, SettlementService), 4 controladores (DeliveryController, RiderController, SettlementController, TrackingController), webhook OrderStatusWebhookController, rutas API en routes/api.php, 2 comandos Artisan (delivery:generate-daily-settlement 23:59, delivery:check-pending-settlements 12:00) registrados en routes/console.php
- **mi3-frontend**: hooks useDeliveryTracking, useRiderGPS, usePendingSettlementBadge; componentes DeliveryMap, OrderPanel, DeliveryMetrics, SettlementPanel, RiderDashboard; páginas /admin/delivery y /rider; badge de alerta en AdminSidebar
- **app3**: página `/tracking/[order_number].astro` (Vista Cliente embebible, realtime via Pusher); iframe de tracking embebido en payment-success.astro para pedidos delivery; env vars PUBLIC_GOOGLE_MAPS_KEY y PUBLIC_REVERB_APP_KEY agregadas en Coolify
- **caja3**: `update_order_status.php` llama webhook mi3 al cambiar estado; página `/delivery-monitor.astro` para operadores
- **Coolify mi3-frontend**: `NEXT_PUBLIC_GOOGLE_MAPS_KEY` agregada vía SSH
- **Pendiente para activar**: commit + deploy + `php artisan migrate` en producción

**Commits:** ninguno aún
**Deploys:** ninguno aún

### 2026-04-14i — Fix timeout bot SuperKiro (session/prompt pegado)

**Cambios:**
- `/opt/kiro-acp-telegram-bot/src/acp-client.js` en VPS: timeout `session/prompt` aumentado de 120s → 600s (10 min). Otros requests mantienen 120s.
- Causa raíz: tareas largas de Kiro (SSH, exploración workspace) superaban 2 min y el bot lanzaba `Timeout: session/prompt`, dejando la sesión sucia.
- Bot reiniciado vía `pm2 restart kiro-telegram-bot`. Nueva sesión ACP activa.

**Commits:** ninguno (cambio directo en VPS)
**Deploys:** ninguno

### 2026-04-14h — Spec fix-sessiones: Task 1 — tests de exploración de bugs creados

**Cambios:**
- Creado `mi3/frontend/lib/__tests__/bug-exploration.test.ts` — 14 tests PBT (fast-check) cubriendo BUG 1, 2, 3, 7, 8. Tests pasaron porque el código ya estaba fixeado en commits anteriores. Sirven como tests de regresión.
- `vitest.config.ts` ya existía (confirmado).

**Commits:** ninguno nuevo
**Deploys:** ninguno

### 2026-04-14g — Spec fix-sessiones: Tasks 3+4+5 ejecutadas (auth loop fix)

**Cambios:**
- `POST /auth/clear-session` endpoint público — expira cookies httpOnly server-side
- `mi3_auth_flag` cookie (non-httpOnly) en login/logout/googleCallback — JS puede borrarla
- `middleware.ts`: checa `mi3_auth_flag` en vez de `mi3_token` — rompe el loop 401
- `api.ts`/`compras-api.ts`: 401 llama clear-session + borra mi3_auth_flag
- Google OAuth: pasa `?token=` en redirect, `TokenFromUrl` component guarda en localStorage
- `useAuth.ts`: `fetchUser()` usa `fetch()` directo (no apiFetch) para evitar loop en /auth/me
- `Dockerfile`: eliminado `key:generate` (APP_KEY persiste via Coolify)

**Commits:** `2c33166`, `246848b`
**Deploys:** mi3-frontend (`hm5ekprg1dfsjz2mwajjyz10`) ✅, mi3-backend (`nml1ab63cplp1wo6fq1okqwz`) ✅

---

> Sesiones anteriores (149 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
