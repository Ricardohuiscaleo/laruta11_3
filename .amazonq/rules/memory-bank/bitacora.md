# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-16)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`632d7f4`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`8c2dbed`) — fix descuento delivery: factor, display, trazabilidad BD |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`3ecd857`) — SPA admin + smart turnos + animations |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`e823d67`) — checklist fix: security shift filter + date:Y-m-d |
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
- [x] **Limpiar datos de prueba delivery** — eliminados 6 pedidos TEST-DLV-* y SIM-*. Pendiente: revertir roles rider de Camila(1), Andrés(3), Dafne(18) cuando termine el testing.
- [ ] Recalcular delivery\_fee server-side en `create_order.php`
- [ ] **Migrar tracking público de app3 a mi3** — `app3/src/pages/tracking/` usa polling HTTP, debería estar en mi3-frontend con Reverb WebSocket nativo para realtime real. Actualmente embebido via iframe en payment-success. Además: ocultar informe técnico al usuario, mostrar tracking en pedidos pending (no solo payment-success), integrar en MiniComandasCliente de app3. No necesario en caja3.
- [x] **Integrar checklists mi3 en caja3** — COMPLETADO. Public/ChecklistController.php con 5 endpoints, ChecklistApp.jsx reescrito para consumir mi3 API. Commit `eaceaab`.
- [x] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143) — CheckoutApp.jsx corregido de 0.6→0.7143, delivery_discount ahora se envía en todos los payloads de CheckoutApp y MenuApp.
- [x] **Fix ShiftService turnos dinámicos + reemplazos** — `generate4x4Shifts()` ahora trackea `reemplazo_seguridad` + `reemplazado_por` para seguridad. Eliminado `monto_reemplazo` falso en dinámicos. Commit `af6e236`.
- [x] **Verificar Google Maps en mi3-frontend** — mapId `d51ca892b68e9c5e5e2dd701` + API key funcionando ✅
- [x] **Deploy spec delivery-tracking-realtime** — commit `70650cf` pusheado. Builds disparados en Coolify. Pendiente verificar builds y ejecutar `php artisan migrate`.
- [x] **Integración caja3/app3 delivery** — webhook en caja3 y iframe en app3 implementados en commit `70650cf`.
- [ ] **Investigar arquitectura SaaS multi-tenant** — AWS Lambda + Aurora PostgreSQL + Amazon Location Service + Stripe. Dominio candidato: pocos.click (caduca 2026-12-21)

---

## Sesiones Recientes

### 2026-04-16b — Fix descuento delivery caja3: factor + display + trazabilidad BD

**Cambios:**
- `CheckoutApp.jsx`: factor descuento corregido de `* 0.6` a `* 0.7143` ($3.500→$2.500). Agregado `deliveryDiscountAmount` y enviado `delivery_discount` en los 5 payloads (TUU, card, cash, pedidosya, transfer).
- `MenuApp.jsx`: agregado `baseDeliveryFee`, `deliveryFee`, `deliveryDiscountAmount` como `useMemo`. `cartTotal` ahora usa fee con descuento. Confirm dialog corregido "40%"→"28%". Display descuento arreglado (mostraba -$0). `delivery_discount` enviado en los 2 payloads.
- Antes el monto cobrado era correcto pero `delivery_discount` siempre se guardaba 0 en BD y el display mostraba -$0.

**Commits:** `8ea3957`, `8c2dbed`
**Deploys:** caja3 ✅ (`8c2dbed`)

### 2026-04-16a — Fix 4 bugs checklist caja3 + data fix BD

**Cambios:**
- `ChecklistService.php`: filtro `turno->tipo` en `crearChecklistsDiarios()` — skip turnos seguridad/reemplazo_seguridad para no generar checklists cajero/planchero a workers de guardia.
- `ChecklistApp.jsx`: `formatCLP()`/`parseCLP()` para formato moneda chilena ($XX.XXX) en input verificación caja. `getPhotoContexto()` + `formData.append('contexto')` para análisis IA independiente por foto.
- `Checklist.php`: cast `scheduled_date` cambiado a `date:Y-m-d` (fix Invalid Date en mi3-frontend).
- BD: reasignado checklist apertura 16-abr de Ricardo→Camila (id=213). Eliminados checklist cierre Ricardo (id=214) y checklist duplicado Camila 0% (id=215).

**Commits:** `e823d67`
**Deploys:** mi3-backend ✅ (`e823d67`), caja3 ✅ (`e823d67`)

### 2026-04-15h — Integración checklists caja3→mi3 + limpieza BD

**Cambios:**
- `Public/ChecklistController.php`: 5 endpoints públicos (today, completeItem, uploadPhoto, verifyCash, complete) sin auth, identifica worker por checklist.personal_id.
- `caja3/ChecklistApp.jsx`: reescrito para consumir mi3 API, auto-detect apertura/cierre, foto upload con AI, verificación caja, progress bar.
- BD: eliminado template "Desenchufar juguera" + 3 items. Fix encoding UTF-8 en templates e items (máquinas, mesón, desagüe). Apertura Camila 15-abr marcada completada.

**Commits:** `eaceaab`, `0afe0ea`
**Deploys:** mi3-backend ✅ (`eaceaab`), caja3 ✅ (`0afe0ea`)

---

> Sesiones anteriores (161 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
