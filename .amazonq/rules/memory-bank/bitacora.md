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
- [x] **Spec fix-sessiones**: COMPLETADO. 8 bugs auth resueltos. Sesiones sobreviven redeploys.
- [x] **Fix duplicate entry turnos** — `updateOrCreate` en ShiftController + ShiftSwapService. Commit `dbe82f8`, deploy `t122hofnf31hazga6zzr5e5v` ✅

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
- [x] **Resolver duplicado Dafne**: migrados 16 turnos + 4 checklists de id=12 → id=18 (user\_id=164). id=12 desactivado, 0 referencias restantes.
- [x] **Deploy pendiente**: Fix nómina dashboard admin — `DashboardController.php` usa `NominaService`. Commit `c68a96b`, deploy `cs1pqigqq5qz1lzc0vlsfd6c` ✅

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

### 2026-04-14f — Fix auth loop infinito + spec bugfix sesiones

**Cambios:**
- `api.ts`/`compras-api.ts`: 401 limpia cookies+localStorage antes de redirigir (previene loop infinito).
- `AuthService`: no borra todos los tokens al login, solo >30 días (multi-dispositivo).
- `AuthController`: devuelve token en JSON. Login guarda en localStorage. Bearer token en todas las peticiones.
- `ImagenService`: nombres únicos S3 (time+random). BD: compra 277 deduplicada.
- Spec bugfix creado: `.kiro/specs/fix-sessiones/` — bugfix.md (8 bugs), design.md (5 properties), tasks.md (9 tareas, 25+ sub-tareas). Auditoría independiente incorporada: BUG 5 descartado, 3 bugs nuevos (7/8/9). APP_KEY confirmado persistente en Coolify.

**Commits:** `82a3f42`, `46e0167`
**Deploys:** mi3-frontend (`c3dfywbm8ao8scqksenizgmt`) ✅, mi3-backend (`zp1qhnm7q86j2qjiz23pac26`) ✅

### 2026-04-14e — Proveedores neto +IVA + normalización ingredientes Vanni

**Cambios:**
- `ExtraccionController`: proveedores que facturan neto (vanni, arauco) → extracción muestra neto tal cual. IVA ×1.19 se aplica al registrar en `CompraController::store()`. Karina Roco → ARIAKA.
- Frontend: indicador IVA azul (Neto vs Con IVA) para proveedores neto. Botón Registrar muestra total con IVA. Fix tipo `ExtractionItem` — campos `notas_descuento` y `descuento`.
- `ImagenService`: fix S3 key collision para nombres únicos. BD: compra 277 deduplicada.
- Auth: `api.ts` + `compras-api.ts` envían Bearer token de localStorage. `AuthController` devuelve token en JSON. Login guarda token en localStorage. Sesiones sobreviven redeploys.
- BD: ingrediente id=40 renombrado. Nuevas equivalencias Vanni. RUT 76.979.850-1 = vanni. Karina Roco = ARIAKA.

**Commits:** `1d0179e`→`3a9180b` (11 commits)
**Deploys:** mi3-backend (`jrf9i38cxe142b679wamudt5`) ✅, mi3-frontend (`zbc8u98mfjwd03olvhb3z8qz`) ✅

---

> Sesiones anteriores (148 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
**Deploys:** mi3-frontend (`xjk16jcai46ne36j36zoun09`) ✅, mi3-backend (`rql7y6p0sj1jrm95q73r4joe`) ✅

---

> Sesiones anteriores (147 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
