# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-13)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`40c3ce4`) |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`40c3ce4`) |
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
| mi3-backend | `php artisan schedule:run` (7 comandos) | `* * * * *` |
| app3 | Gmail Token Refresh | `*/30 * * * *` |
| caja3 | Daily Checklists (legacy) | `0 12 * * *` (8 AM Chile) |

### Bot Telegram (SuperKiro)

| Componente | Estado |
|-----------|--------|
| Bot | `@SuperKiro_bot` — pm2 auto-start en VPS |
| kiro-cli | v1.29.8 en `/root/.local/bin/kiro-cli` (Builder ID) |
| Workspace | `/root/laruta11_3` ✅ verificado |
| ACP | Sesión activa, acceso completo al monorepo |

---

## Tareas Pendientes

### 🔴 Críticas (afectan producción)

- [ ] **Actualizar `checklist_templates`** para creación diaria con items correctos del planchero (3 fotos: plancha, lavaplatos, mesón)
- [ ] **Corregir caja3 `get_turnos.php`** base date cajero (2026-02-01 → 2026-02-02)
- [ ] **Generar turnos mayo** en producción
- [ ] **Fix push subscriptions duplicadas** en `push_subscriptions_mi3` (44 registros para 1 usuario)

### 🟡 Verificaciones pendientes

- [ ] Verificar prompts IA planchero dan feedback correcto (plancha/lavaplatos/mesón)
- [ ] Verificar upload S3 en compras (end-to-end)
- [ ] Verificar Gmail Token Refresh funciona 100%
- [ ] Verificar subida masiva agrupa ARIAKA correctamente

### 🟢 Mejoras futuras

- [ ] Tareas generadas por IA desde fotos de checklist (si score < 50 → tarea automática)
- [ ] Decidir si eliminar/desactivar Portal R11 legacy (`app.laruta11.cl/r11/`)
- [ ] Ejecutar migraciones `checklists_v2` en producción (spec existe pero tabla no)
- [ ] Recalcular delivery\_fee server-side en `create_order.php` (actualmente confía en frontend)
- [ ] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143)

---

## Sesiones Recientes

### 2026-04-12be — Fotos planchero + HEIC fix + Bot Telegram

**Cambios:**
- Frontend: detección 5 tipos de contexto foto (plancha/lavaplatos/mesón/exterior/interior)
- Frontend: fallback HEIC — si compresión falla, sube original sin bloquear
- Backend: 6 prompts IA nuevos para planchero + formato dinámico Bedrock
- BD producción: planchero actualizado a 3 fotos específicas (items #1743-#1748)
- Bot `@SuperKiro_bot` configurado: kiro-cli ACP en VPS, pm2, workspace verificado
- Hook telegram-notify migrado a SuperKiro-LaRuta11

**Commits:** `71ef7c4`, `3345893`, `40c3ce4`, `63e4cf2`, `695f38d`
**Deploys:** mi3-frontend (`v60wqb8t`) ✅, mi3-backend (`sfpfjkuf`) ✅

### 2026-04-12bd — Prompts IA mejorados + cierre 18:00

**Cambios:**
- 4 prompts IA reescritos con feedback real (vitrina=bebidas, no plancha, no TV interior)
- Cierre solo visible después 18:00 Chile
- Fotos agregadas al planchero (#179, #180)

**Commits:** `649ebd7`
**Deploys:** mi3-frontend (`saaf4bup`) ✅, mi3-backend (`cctihh1o`) ✅

### 2026-04-12bc — Feedback IA visible en checklist

**Cambios:**
- Score badge (0-100) + observaciones debajo de cada foto
- Fix response field: `url` (no `photo_url`)

**Commits:** `c17a17a`
**Deploys:** mi3-frontend (`kzpr8hws`) ✅

---

> Sesiones anteriores (104 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
