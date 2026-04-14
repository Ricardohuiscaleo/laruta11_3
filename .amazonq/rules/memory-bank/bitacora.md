# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-14)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`351753d`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | 🔄 Pendiente verificar (`351753d`) |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`b32f647`) — delivery tracking activo |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`351753d`) — migraciones delivery ejecutadas |
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
- [ ] **Agregar `mi.laruta11.cl` a restricciones HTTP de Google Maps API key** en Google Cloud Console (el mapa no carga sin esto)
- [x] **Deploy spec delivery-tracking-realtime** — commit `70650cf` pusheado. Builds disparados en Coolify. Pendiente verificar builds y ejecutar `php artisan migrate`.
- [x] **Integración caja3/app3 delivery** — webhook en caja3 y iframe en app3 implementados en commit `70650cf`.
- [ ] **Investigar arquitectura SaaS multi-tenant** — AWS Lambda + Aurora PostgreSQL + Amazon Location Service + Stripe. Dominio candidato: pocos.click (caduca 2026-12-21)

---

## Sesiones Recientes

### 2026-04-14l — Deploy delivery-tracking-realtime: fixes build + deploys activos

**Cambios:**
- Fix 1: `app3/src/pages/tracking/[order_number].astro` → error `Expected "}" but found "."`. Commit `10cead8`.
- Fix 2: app3 es `output: 'static'`, ruta dinámica requiere SSR. Reemplazada por `tracking/index.astro` estática con `?order=`. Commit `351753d`.
- Fix 3: mi3-frontend fallaba con `npm ci` por `@vis.gl/react-google-maps` faltante en package-lock.json. Commit `f41e223`.
- Fix 4: `</nav>` duplicado en AdminSidebar.tsx causaba `Expected ',', got 'className'`. Commit `b32f647`.
- mi3-backend: deployado ✅ (`351753d`). Migraciones ejecutadas: rider_locations, delivery_assignments, daily_settlements, campos tuu_orders.
- app3: deployado ✅ (`351753d`).
- caja3: deploy en progreso (`351753d`).
- mi3-frontend: deployado ✅ (`b32f647`). WebSocket conectado, paneles funcionales. Google Maps requiere agregar `mi.laruta11.cl` en restricciones de la API key en Google Cloud Console.

**Commits:** `10cead8`, `351753d`, `f41e223`, `b32f647`
**Deploys:** mi3-backend ✅, app3 ✅, mi3-frontend ✅, caja3 pendiente verificar

### 2026-04-14k — Deploy delivery-tracking-realtime: commit inicial

**Cambios:**
- Commit `70650cf` — 44 archivos, 4736 inserciones. Todo el spec delivery-tracking-realtime pusheado a main.

**Commits:** `70650cf`
**Deploys:** builds fallaron (errores de build corregidos en sesión 14l)

### 2026-04-14j — Spec delivery-tracking-realtime: implementación completa

**Cambios:**
- **mi3-backend**: 4 migraciones, 3 modelos Eloquent, 2 eventos Reverb, channels.php, 3 servicios, 4 controladores + webhook, rutas API, 2 comandos Artisan en scheduler
- **mi3-frontend**: hooks useDeliveryTracking/useRiderGPS/usePendingSettlementBadge; Vista Monitor /admin/delivery; Vista Rider /rider; badge alerta en AdminSidebar
- **app3/caja3**: tracking page, webhook, delivery-monitor; env vars en Coolify

**Commits:** ninguno (código local)
**Deploys:** ninguno

### 2026-04-14i — Fix timeout bot SuperKiro (session/prompt pegado)

**Cambios:**
- `/opt/kiro-acp-telegram-bot/src/acp-client.js` en VPS: timeout `session/prompt` aumentado de 120s → 600s (10 min).

**Commits:** ninguno (cambio directo en VPS)
**Deploys:** ninguno

---

> Sesiones anteriores (151 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
