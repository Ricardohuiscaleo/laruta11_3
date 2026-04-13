# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-13)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`534e73e`) |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`b66785d`) |
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
- [ ] **Deploy pendiente**: fix duplicate entry turnos (`updateOrCreate` en ShiftController + ShiftSwapService) — falta commit+deploy

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
- [x] **Resolver duplicado Dafne**: migrados 16 turnos + 4 checklists de id=12 → id=18 (user\_id=164). id=12 desactivado, 0 referencias restantes.

### 🟢 Mejoras futuras

- [x] **Sistema de Rendiciones**: Implementado. Tabla `rendiciones`, página pública /rendicion/{token}, saldo encadenado, 250 compras históricas marcadas como rendidas, saldo actual $68.899
- [x] Tareas generadas por IA desde fotos de checklist (si score < 50 → tarea automática) — implementado: `checklist_ai_tasks`, escalamiento a 3 detecciones, tab Test IA
- [x] **Verificar saldo en caja interactivo** — implementado: item_type cash_verification, tarjeta interactiva Sí/No, notificaciones via @laruta11_bot + push. Commit `7eb206d`
- [ ] Obtener chat_id del grupo "Pedidos 11" para notificaciones de caja (actualmente usa chat personal)
- [ ] Ejecutar migraciones `checklists_v2` en producción (spec existe pero tabla no)
- [ ] Recalcular delivery\_fee server-side en `create_order.php` (actualmente confía en frontend)
- [ ] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143)

---

## Sesiones Recientes

### 2026-04-13y — Fix duplicate entry turnos (reemplazo)

**Cambios:**
- `ShiftController.php`: `Turno::create()` → `Turno::updateOrCreate()` para evitar violación unique constraint `(personal_id, fecha)` al crear turnos de reemplazo
- `ShiftSwapService.php`: mismo fix en `aprobar()` — actualiza turno existente en vez de insertar duplicado

**Commits:** pendiente
**Deploys:** pendiente

### 2026-04-13x — Fix nómina: excluir cashflow dueño + liquidez correcta

**Cambios:**
- `PayrollController.php`: filtrar rol "dueño" del cálculo de `total_sueldos` por centro de costo
- `LiquidacionService.php`: `getCashflowLiquidez()` ahora calcula ventas - compras - sueldos (antes solo sumaba ventas brutas TUU)
- Fórmula: ventas (installment - delivery, paid) - compras - sueldos Ruta 11 (activos, sin seguridad-only, sin dueño)
- Arreglado en mi3 + caja3 `get_monthly_cashflow.php` (misma query)

**Commits:** `38285fb`, `c344ac2`, `d30bf91`, `b66785d`
**Deploys:** mi3-backend (`otmdd4be`) ✅, caja3 (`p13nq021`) ✅

### 2026-04-13w — Feature: Verificación caja + AI Training + Tab Test IA

**Cambios:**
- 6 migraciones: `item_type` en templates/items, `cash_*` en items, 3 tablas nuevas (`checklist_ai_prompts`, `checklist_ai_training`, `checklist_ai_tasks`), seed 12 prompts
- 3 modelos nuevos: `ChecklistAiPrompt`, `ChecklistAiTraining`, `ChecklistAiTask`
- `TelegramService`: multi-bot (SuperKiro + @laruta11_bot)
- `ChecklistService`: `verificarCaja()` con notificaciones Telegram + push
- `PhotoAnalysisService`: prompts desde BD, `buildEnhancedPrompt` con correcciones + tareas, `registrarTareasSiNecesario` con escalamiento
- `AITrainingService`: feedback, precisión, auto-generación de prompts candidatos
- 8 rutas API nuevas, 2 componentes frontend (CashVerificationItem + TabTestIA)
- Coolify: env vars `TELEGRAM_LARUTA11_TOKEN` + `TELEGRAM_LARUTA11_CHAT_ID`
- BD: templates `cash_verification` para cajero apertura+cierre, checklists recreados con saldo $19.311
- Admin view: `ChecklistItemDetail` muestra saldo esperado, resultado ok/discrepancia con colores

**Commits:** `7eb206d`, `534e73e`
**Deploys:** mi3-backend (`vzjjvl7z`) ✅, mi3-frontend (`tr6busob`, `z4p1p7dt`) ✅

### 2026-04-13v — Telegram notificación en fallo de cron + retry

**Cambios:**
- `TelegramService.php`: nuevo servicio para enviar mensajes via Telegram Bot API
- `console.php`: `onFailure` ahora envía alerta Telegram con nombre del cron, error, y hint `/retry`
- `config/services.php`: config `telegram.token` + `telegram.chat_id` desde env vars
- Coolify: env vars `TELEGRAM_TOKEN` + `TELEGRAM_CHAT_ID` agregadas a mi3-backend

**Commits:** `2cc350f`
**Deploys:** mi3-backend (`whpy117v`) ✅

---

> Sesiones anteriores (133 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
