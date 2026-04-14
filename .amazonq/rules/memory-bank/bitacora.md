# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-14)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`913b5ec`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`913b5ec`) |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`2d50f13`) |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`f9b69c5`) |
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

### 2026-04-14e — Proveedores neto +IVA + normalización ingredientes Vanni

**Cambios:**
- `ExtraccionController`: proveedores que facturan neto (vanni, arauco) → precios ×1.19 automático para registrar con IVA incluido.
- BD: ingrediente id=40 renombrado "Caja Sandwich" → "ENVASE CARTULINA SANDWICH GRANDE PAMOLSA". Nuevas equivalencias: ENVASE CARTULINA, ENVASE ALUMINIO, ENVASE HOT DOG BIO-VANNI. RUT 76.979.850-1 corregido a vanni.

**Commits:** `1d0179e`, `f9b69c5`
**Deploys:** mi3-backend (`ckpn4g064cz1vsc6421l4ozk`) ✅

### 2026-04-14d — IA descuentos + estructura boleta + equivalencias paquete→unidades + Cencosud

**Cambios:**
- Prompt IA: estructura boleta supermercado chileno (secciones: encabezado→productos→subtotal→fiscal→puntos→voucher). Campo `descuento` por item, backend aplica resta en `normalizeAmounts`.
- `ExtraccionController`: post-match aplica `product_equivalences` — convierte paquetes a unidades individuales (ej: 2 paq Big Montina = 20 unidades). Recalcula precio_unitario. Cencosud/Jumbo/Santa Isabel en patrones. Fuzzy match con normalización de acentos.
- Frontend: badge naranja 🏷️ `notas_descuento`, badge azul 📦 `empaque_detalle`. Tipo `RegistroItem` actualizado.
- `getChecklistsAdmin`: shift-day solo cuando fecha=hoy Y 00:00-04:00.
- BD: Jumbo/Santa Isabel en `supplier_index`. RUT 76.979.850-1 corregido: era ideal, ahora vanni. Equivalencias: Big Montina 800GR, Salchicha Sureña → Montina Big (10u/paq). Crema Larga Vida → Crema (200ml). Champiñón variantes → champiñón (Bandeja) + fix doble-encoding UTF-8 ñ. Envase Cartón/Cartulina → Caja Sandwich. Envase Aluminio C-20 → Caja aluminio c/tapa (M). Nuevo ingrediente: Envase Hot Dog 340-VAMNET (id=155, Packaging).

**Commits:** `cce9b31`→`c2a80da` (13 commits)
**Deploys:** mi3-backend (`q6wf0vgq2o3mradc3cdg2079`) ✅, mi3-frontend (`z8mvpf6x0miwtdwmuy20j1h0`) ✅

### 2026-04-14c — Fix checklist turno nocturno + shift-day logic alineada con caja3

**Cambios:**
- Backend `ChecklistService`: shift-day logic (00:00-04:00 = día anterior, igual que caja3). Cierre `scheduled_time` corregido de 02:00→00:45. Admin view: shift-day solo aplica cuando fecha=hoy Y 00:00-04:00 (no en navegación manual).
- Backend `ChecklistController`: on-demand creation busca turnos en fecha actual y shift-date. Fecha calculada con timezone Chile.
- Frontend `checklist/page.tsx`: cierre visible 00:00-04:00 (turno nocturno) y 18:00+, oculto 04:00-18:00.
- BD: corregido `scheduled_time` de checklists cierre existentes. Eliminado checklist corrupto id=188 (personal_id NULL).
- También incluye fix compras: `metodo_pago` enum `debit`→`card` + validación `in:` en CompraController.

**Commits:** `b15e673`, `43323cf`, `cce9b31`
**Deploys:** mi3-frontend (`xjk16jcai46ne36j36zoun09`) ✅, mi3-backend (`rql7y6p0sj1jrm95q73r4joe`) ✅

### 2026-04-14b — IA báscula feria + equivalencias empaque + modal foto + feedback visible

**Cambios:**
- Backend: prompt Bedrock mejorado TIPO 3 (báscula feria): lee PESO/PRECIO/TOTAL, notación abreviada (45=$4.500), identifica producto visual
- Backend: `normalizeAmounts` con safety net para precios abreviados en tipo bascula
- Frontend: click en thumbnail abre modal fullscreen, feedback IA visible (notas_ia en badge azul, errores en badge rojo), check verde en extracción exitosa
- BD: 12 equivalencias nuevas en `product_equivalences`: saco papa 25kg, caja tomate 18kg, caja palta 6kg, caja cebolla 18kg, caja lechuga 12u, caja pan brioche 50u, bidón aceite 20kg, etc.

**Commits:** `66e604a`, `fa311ef`
**Deploys:** mi3-frontend (`j6gwotmzzq6bpdp48gio2f10`) ✅, mi3-backend (`e291smci6yin5xtvl3cfd97g`) ✅

---

> Sesiones anteriores (145 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
