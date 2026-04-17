# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-16)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`632d7f4`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`2f8f3dc`) — hide Venta TV + fix descuento delivery |
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
| Buzón bidireccional | ✅ `/tmp/kiro-ask.json` → Telegram → `/tmp/kiro-reply.json` — Kiro IDE puede preguntar via SSH |

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

### 2026-04-17c — Spec recipe-management-ai: frontend completo + Chef_Bot completo (tareas 5.2-11)

**Cambios:**
- mi3-frontend: 5 páginas de recetas creadas — `page.tsx` (listado con search/sort/filter/click-to-detail), `[productId]/page.tsx` (detalle/edición con IngredientAutocomplete + RecipeForm + CostBadge), `ajuste-masivo/page.tsx` (form→preview→success con validación negativos), `recomendaciones/page.tsx` (tabla comparativa con margen objetivo 65%), `auditoria/page.tsx` (stock audit con CSV export). `RecetasSection.tsx` actualizado con 4 tabs lazy-loaded.
- chef-bot/: Proyecto Node.js completo creado — `ai/bedrockClient.js` (Nova Micro + Converse API + retry), `ai/promptBuilder.js` (schema + ejemplos NL→SQL), `ai/responseParser.js` (JSON + markdown code blocks), `guards/sqlGuard.js` (validación + parametrización + logging), `handlers/messageHandler.js` (flujo query/modify + auth + help), `handlers/callbackHandler.js` (confirm/cancel inline keyboard), `formatters/telegramFormatter.js` (recipe/stock/generic + Levenshtein fuzzy), `api/recipeApi.js` (HTTP client + executeModification), `logger.js` (audit logging), `index.js` (entry point + graceful shutdown), `ecosystem.config.js` (pm2).
- Tests: 31 tests AI engine + 46 tests SQL_Guard + 33 tests formatter = 110 unit tests.
- Pendiente: `npm install` en VPS, configurar env vars, `pm2 start`, commit + deploy mi3-frontend y mi3-backend.

**Commits:** pendiente
**Deploys:** pendiente

### 2026-04-17b — Spec recipe-management-ai: backend + frontend layout (tareas 1-5.1)

**Cambios:**
- mi3-backend: `ProductRecipe` model, `RecipeService` (cost calc, CRUD, bulk adjustment, recommendations, stock audit, CSV export), `RecipeController` con 10 endpoints, 41 unit tests.
- mi3-frontend: `RecetasSection` con tabs (Listado, Ajuste Masivo, Recomendaciones, Auditoría), link en sidebar + mobile nav con ChefHat icon.
- Rutas API: `/api/v1/admin/recetas/*` (CRUD + bulk + recommendations + audit).

**Commits:** pendiente (sin deploy aún)
**Deploys:** pendiente

### 2026-04-17a — Buzón bidireccional Kiro IDE ↔ Telegram

**Cambios:**
- VPS `/opt/kiro-acp-telegram-bot/src/telegram.js`: sistema de buzón con flujo conversacional. Watcher cada 3s en `/tmp/kiro-ask.json`, envía pregunta con contexto a Telegram, confirma "📨 esperando respuesta", guarda reply en `/tmp/kiro-reply.json`, confirma "✅ respuesta enviada" con resumen. Timeout 10 min.
- Bot reiniciado via pm2. Kiro IDE puede preguntar al usuario via SSH→VPS→Telegram y leer respuestas.

**Commits:** N/A (cambio directo en VPS)
**Deploys:** bot pm2 restart ✅

### 2026-04-16b — Fix descuento delivery caja3: factor + display + trazabilidad BD

**Cambios:**
- `CheckoutApp.jsx`: factor descuento corregido de `* 0.6` a `* 0.7143` ($3.500→$2.500). Agregado `deliveryDiscountAmount` y enviado `delivery_discount` en los 5 payloads (TUU, card, cash, pedidosya, transfer).
- `MenuApp.jsx`: agregado `baseDeliveryFee`, `deliveryFee`, `deliveryDiscountAmount` como `useMemo`. `cartTotal` ahora usa fee con descuento. Confirm dialog corregido "40%"→"28%". Display descuento arreglado (mostraba -$0). `delivery_discount` enviado en los 2 payloads.

**Commits:** `8ea3957`, `8c2dbed`, `2f8f3dc`
**Deploys:** caja3 ✅ (`2f8f3dc`)

---

> Sesiones anteriores (162 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
