# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-13)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`e82e9a9`) |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`2cc350f`) |
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
| caja3 | Daily Checklists (legacy) | ❌ Desactivado (mi3 lo reemplaza) |

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

- [x] **Actualizar `checklist_templates`** — overhaul completo: 23 templates nuevos con rol explícito (cajero/planchero), fotos planchero separadas (plancha+freidora / lavaplatos+mesón), prompts IA combinados. Cron legacy caja3 desactivado.
- [ ] **Corregir caja3 `get_turnos.php`** base date cajero (2026-02-01 → 2026-02-02)
- [ ] **Generar turnos mayo** en producción
- [ ] **Fix push subscriptions duplicadas** en `push_subscriptions_mi3` (44 registros para 1 usuario)

### 🟡 Verificaciones pendientes

- [x] **Ajustes muestra IDs en vez de nombres** — fix: map personal_nombre en AdjustmentController
- [x] **Inasistencias automáticas $40.000** — falsos positivos confirmados (código viejo pre-fix D-1). Eliminados ajustes ids 24,27 de Andrés Aguilera. Cron `dailyAt('02:00')` + D-1 fix funcionando correctamente
- [x] **Nómina vacía** — fix: PayrollController transforma {ruta11,seguridad} → {resumen,centros}. Commit `985ea06`, deploy `z12naxr` ✅
- [x] **Editar trabajadores: "validation.array"** — fix: rol string→array en personal/page.tsx
- [x] **Deploy pendiente**: `foto_url` en `personal` — committeado y deployado en 75e15b0
- [x] **Rotate foto 500** — fix: `use Illuminate\Http\Request` en PersonalController. Commit `5e0dab8`, deploy `985ea06` ✅
- [x] Verificar prompts IA planchero dan feedback correcto (plancha/lavaplatos/mesón) — prompts actualizados: `plancha_*`, `lavaplatos_meson_*` combinados
- [ ] Verificar upload S3 en compras (end-to-end)
- [ ] Verificar Gmail Token Refresh funciona 100%
- [ ] Verificar subida masiva agrupa ARIAKA correctamente
- [ ] **Deploy pendiente**: `foto_url` en `personal` — BD ya tiene columna+datos, falta commit+deploy del código backend+frontend
- [ ] **Resolver duplicado Dafne**: id=12 (sin user\_id, turnos apuntan acá) vs id=18 (user\_id=164, con foto). Migrar turnos/checklists al 18 y desactivar 12

### 🟢 Mejoras futuras

- [x] **Sistema de Rendiciones**: Implementado. Tabla `rendiciones`, página pública /rendicion/{token}, saldo encadenado, 250 compras históricas marcadas como rendidas, saldo actual $68.899
- [ ] Tareas generadas por IA desde fotos de checklist (si score < 50 → tarea automática)
- [ ] **Verificar saldo en caja interactivo** — tarjeta con monto de `caja_movimientos`, confirmación sí/no, input diferencia, notificación admin (feature nueva)
- [ ] Ejecutar migraciones `checklists_v2` en producción (spec existe pero tabla no)
- [ ] Recalcular delivery\_fee server-side en `create_order.php` (actualmente confía en frontend)
- [ ] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143)

---

## Sesiones Recientes

### 2026-04-13v — Telegram notificación en fallo de cron + retry

**Cambios:**
- `TelegramService.php`: nuevo servicio para enviar mensajes via Telegram Bot API
- `console.php`: `onFailure` ahora envía alerta Telegram con nombre del cron, error, y hint `/retry`
- `config/services.php`: config `telegram.token` + `telegram.chat_id` desde env vars
- Coolify: env vars `TELEGRAM_TOKEN` + `TELEGRAM_CHAT_ID` agregadas a mi3-backend

**Commits:** `2cc350f`
**Deploys:** mi3-backend (`whpy117v`) ✅

### 2026-04-13u — Overhaul checklist templates + AI prompts lavaplatos+mesón

**Cambios:**
- BD producción: desactivados 23 templates viejos, creados 23 nuevos con rol explícito (cajero/planchero, sin NULL)
- BD producción: borrados checklists de hoy (ids 189-192), recreados 4 nuevos (ids 193-196) con items correctos via `mi3:create-daily-checklists`
- Fotos planchero: "Sector plancha, freidora y fuentes" + "Lavaplatos y mesón de trabajo" (apertura y cierre)
- Fotos cajero: interior + exterior (se mantienen)
- Removido "Verificar saldo en caja" de templates (será feature interactiva aparte)
- `PhotoAnalysisService.php`: nuevos prompts `lavaplatos_meson_apertura` y `lavaplatos_meson_cierre`

**Commits:** `9ecee87`
**Deploys:** mi3-backend (`x6vdz1v3`) ✅

### 2026-04-13t — Fix 500 crear turno reemplazo + UX monto seleccionable

**Cambios:**
- `ShiftController.php`: try-catch en `store()` para capturar error real de BD en vez de 500 genérico
- `turnos/page.tsx`: monto reemplazo ahora con botones rápidos $20.000/$30.000 + input "Otro" para monto custom

**Commits:** `e82e9a9`
**Deploys:** mi3-backend + mi3-frontend (push → Coolify auto-deploy)

### 2026-04-13s — Desactivar cron legacy caja3 + template gas planchero

**Cambios:**
- BD producción: INSERT `checklist_templates` id=23 (apertura/planchero: "Conectar conexiones de gas")
- BD producción: DELETE checklists legacy ids 178,187 (sin personal_id, hoy)
- Coolify: scheduled task `Daily Checklists (caja3)` → `enabled: false` (UUID: `m3rws04ajruudvng66n5qb1d`)
- Solo mi3 `create-daily-checklists` crea checklists ahora

---

> Sesiones anteriores (130 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
