# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-17)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`632d7f4`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`2f8f3dc`) — hide Venta TV + fix descuento delivery |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`915b894`) — recipe-management-ai: 5 páginas recetas + fix recomendaciones |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`ec38aa7`) — Recipe API: 10 endpoints CRUD + bulk + recommendations + audit |
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

### Bots Telegram

| Bot | Componente | Estado |
|-----|-----------|--------|
| `@SuperKiro_bot` | kiro-telegram-bot (pm2 id 0) | ✅ Running — buzón bidireccional Kiro IDE ↔ Telegram |
| `@ChefR11_bot` | chef-bot (pm2 id 3) | ✅ Running — recipe AI bot, Nova Micro + SQL_Guard + MySQL |

| Componente | Estado |
|-----------|--------|
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

### 2026-04-17e — Chef_Bot: conversational RAG agent + full DB schema + AWS credentials fix

**Cambios:**
- `chef-bot/ai/promptBuilder.js`: Rediseño completo — agente conversacional con personalidad "Chef R11", esquema completo de BD (products, ingredients, product_recipes, categories, subcategories, tuu_orders, tuu_order_items, tv_orders, inventory_transactions, compras, compras_detalle, combos), ejemplos de saludos, ventas, stock, cambios masivos, descripciones.
- `chef-bot/ai/responseParser.js`: Soporte para 5 intents (chat, query, modify, api_action, bulk_action). Fallback a chat si JSON parse falla.
- `chef-bot/handlers/messageHandler.js`: Router por intent, manejo de mensajes largos (split 4096 chars), fallback sin Markdown si parse error, auth check centralizado.
- `chef-bot/guards/sqlGuard.js`: Allowlist expandida a 12 tablas (+ tuu_orders, tuu_order_items, tv_orders, tv_order_items, inventory_transactions, compras, compras_detalle, categories, subcategories, combos, combo_items).
- `mi3/frontend/app/admin/recetas/recomendaciones/page.tsx`: Fix field names mismatch — `price→current_price`, `margin→current_margin` para coincidir con backend.
- VPS: AWS credentials configuradas en `~/.aws/credentials` para Bedrock access. Bot reiniciado via pm2.
- `chef-bot/formatters/telegramFormatter.js`: Fix conversión de unidades en cálculo de costos — agregado `UNIT_CONVERSIONS` (kg↔g, L↔ml) y `calculateIngredientCost()`. Tomate 150g×$500/kg ahora muestra $75 (antes $75.000).

**Commits:** `84daa6c`, `915b894`, `fb25e62`
**Deploys:** chef-bot pm2 restart ✅, mi3-frontend ✅ (`915b894`)

### 2026-04-17d — Deploy spec recipe-management-ai: mi3-frontend + mi3-backend + Chef_Bot pm2

**Cambios:**
- `chef-bot/ecosystem.config.js`: removidas credenciales hardcodeadas, ahora usa `process.env` con fallbacks.
- Deploy mi3-frontend y mi3-backend via Coolify API — ambos `finished` ✅.
- Chef_Bot (`@ChefR11_bot`) iniciado en VPS via pm2 (id 3, pid 2376466) con env vars: DB_HOST=10.0.1.7 (MySQL Docker), DB_USER=laruta11_user, DB_NAME=laruta11. `pm2 save` ejecutado.
- Spec recipe-management-ai: tarea 11 marcada como completada. Todas las tareas requeridas done.

**Commits:** `ec38aa7`
**Deploys:** mi3-frontend ✅, mi3-backend ✅, chef-bot pm2 ✅

### 2026-04-17c — Spec recipe-management-ai: frontend completo + Chef_Bot completo (tareas 5.2-11)

**Cambios:**
- mi3-frontend: 5 páginas de recetas creadas — `page.tsx` (listado con search/sort/filter/click-to-detail), `[productId]/page.tsx` (detalle/edición con IngredientAutocomplete + RecipeForm + CostBadge), `ajuste-masivo/page.tsx` (form→preview→success con validación negativos), `recomendaciones/page.tsx` (tabla comparativa con margen objetivo 65%), `auditoria/page.tsx` (stock audit con CSV export). `RecetasSection.tsx` actualizado con 4 tabs lazy-loaded.
- chef-bot/: Proyecto Node.js completo creado — `ai/bedrockClient.js` (Nova Micro + Converse API + retry), `ai/promptBuilder.js` (schema + ejemplos NL→SQL), `ai/responseParser.js` (JSON + markdown code blocks), `guards/sqlGuard.js` (validación + parametrización + logging), `handlers/messageHandler.js` (flujo query/modify + auth + help), `handlers/callbackHandler.js` (confirm/cancel inline keyboard), `formatters/telegramFormatter.js` (recipe/stock/generic + Levenshtein fuzzy), `api/recipeApi.js` (HTTP client + executeModification), `logger.js` (audit logging), `index.js` (entry point + graceful shutdown), `ecosystem.config.js` (pm2).
- Tests: 31 tests AI engine + 46 tests SQL_Guard + 33 tests formatter = 110 unit tests.

**Commits:** `97b23a3`
**Deploys:** pendiente → deployado en sesión 17d

### 2026-04-17b — Spec recipe-management-ai: backend + frontend layout (tareas 1-5.1)

**Cambios:**
- mi3-backend: `ProductRecipe` model, `RecipeService` (cost calc, CRUD, bulk adjustment, recommendations, stock audit, CSV export), `RecipeController` con 10 endpoints, 41 unit tests.
- mi3-frontend: `RecetasSection` con tabs (Listado, Ajuste Masivo, Recomendaciones, Auditoría), link en sidebar + mobile nav con ChefHat icon.
- Rutas API: `/api/v1/admin/recetas/*` (CRUD + bulk + recommendations + audit).

**Commits:** `97b23a3`
**Deploys:** pendiente → deployado en sesión 17d

---

> Sesiones anteriores (164 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
