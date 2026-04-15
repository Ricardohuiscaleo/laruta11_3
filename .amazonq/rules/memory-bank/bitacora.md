# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-15)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`72e348c`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | 🔄 Pendiente verificar (`351753d`) |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`3ecd857`) — SPA admin + smart turnos + animations |
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
- [ ] **Fix ShiftService turnos dinámicos + reemplazos** — cuando se crea un reemplazo manual, el turno dinámico del titular sigue apareciendo. `generate4x4Shifts()` debe suprimir dinámicos cuando existe un turno de reemplazo para esa persona/fecha.
- [x] **Verificar Google Maps en mi3-frontend** — mapId `d51ca892b68e9c5e5e2dd701` + API key funcionando ✅
- [x] **Deploy spec delivery-tracking-realtime** — commit `70650cf` pusheado. Builds disparados en Coolify. Pendiente verificar builds y ejecutar `php artisan migrate`.
- [x] **Integración caja3/app3 delivery** — webhook en caja3 y iframe en app3 implementados en commit `70650cf`.
- [ ] **Investigar arquitectura SaaS multi-tenant** — AWS Lambda + Aurora PostgreSQL + Amazon Location Service + Stripe. Dominio candidato: pocos.click (caduca 2026-12-21)

---

## Sesiones Recientes

### 2026-04-15g — Smart replacement flow + dojo calendar enhancements

**Cambios:**
- Dojo calendar rewrite (`8a5debe`): grid mensual desktop, scroll horizontal mobile, avatares con fotos/bordes por rol, panel detalle con asignar contextual.
- Smart replacement flow (`8aa5ce8`): panel detalle separado en 🍔 R11 y 🛡️ Seguridad, X en avatar crea vacante (dashed circle), panel "¿Quién reemplaza?" con disponibles filtrados por rol, auto-asigna con montos correctos ($20k R11 / $30k Seguridad), planchero "gestiona internamente".
- Profile modal: avatar grande, info turno, detalles reemplazo, stats mensuales.
- Fix cross-role filter (`2d4b479`): seguridad workers disponibles aunque trabajen en R11 ese día.
- Fix profile modal text + silent refresh + animations (`3ecd857`): "Reemplaza a" correcto, sin reload al asignar, vacancy pulse, avatar scale, modal backdrop-blur.
- BD: eliminado turno test Ricardo 12-abr.

**Commits:** `8a5debe`, `8aa5ce8`, `dedb1b1`, `2d4b479`, `3ecd857`
**Deploys:** mi3-frontend ✅ (`3ecd857`)

### 2026-04-15f — Dojo-style turnos calendar rewrite

**Cambios:**
- TurnosSection reescrito completo: grid calendario mensual en desktop (cards grandes con día semana, número, count), scroll horizontal en mobile con auto-scroll al hoy.
- Avatares circulares con fotos y bordes por rol (amber=cajero, green=planchero, red=seguridad), iniciales fallback.
- Panel detalle "Hoy trabajan en R11" con avatares al seleccionar un día.
- Botón "Asignar" movido de header a contextual "+" en panel detalle, pre-llena fecha seleccionada.

**Commits:** `8a5debe`
**Deploys:** mi3-frontend ✅ (`8a5debe`)

### 2026-04-15e — UX: sidebar colapsable, turnos smart, mobile polish

**Cambios:**
- Sidebar colapsable w-64↔w-16 con localStorage, logo R11HEADER.jpg, React.memo NavItem, nav consolidado, transiciones suaves, tooltips en modo collapsed.
- Turnos: barra resumen "Hoy trabaja" + turnos/persona, leyenda colores, Dafne purple (id=18), celdas 80px desktop, icono reemplazo, pulsing dot hoy, vista lista en mobile.
- Mobile: título dinámico en header (SECTION_TITLES), haptic feedback, backdrop-blur en sheet, animación slide-up, red dot en "Más" para badges secundarios.

**Commits:** `6207c52`
**Deploys:** mi3-frontend ✅ (`6207c52`)

---

> Sesiones anteriores (158 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
