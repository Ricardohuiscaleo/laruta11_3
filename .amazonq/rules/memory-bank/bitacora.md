# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-18)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`632d7f4`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`9025e58`) — pedidosya_cash: fix root cause - pedidosya_price en get_menu_products.php |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`9710275`) — pipeline multi-modelo + consola debug |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`9710275`) — pipeline multi-modelo + extraction-logs API |
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

### 2026-04-18a — Spec compras-pipeline-multimodelo: Rekognition + Nova Micro + Nova Pro + SSE

**Cambios:**
- `mi3/backend/app/Services/Compra/AwsSignatureService.php`: Nuevo — SigV4 signing reutilizable con soporte curl_multi y headers custom.
- `mi3/backend/app/Services/Compra/RekognitionService.php`: Nuevo — DetectLabels + DetectText en paralelo via curl_multi.
- `mi3/backend/app/Services/Compra/ClasificadorService.php`: Nuevo — Nova Micro clasifica tipo imagen (boleta/factura/producto/bascula/transferencia) + carga contexto BD filtrado por tipo.
- `mi3/backend/app/Services/Compra/AnalisisService.php`: Nuevo — Nova Pro con prompts específicos por tipo (~400-800 tokens vs ~4000 del monolítico anterior).
- `mi3/backend/app/Services/Compra/PipelineExtraccionService.php`: Nuevo — orquestador 3 fases con callback SSE, post-processing completo (mapPersonToSupplier, matchProveedorByRut, applySupplierRules, product equivalences).
- `mi3/backend/app/Http/Controllers/Admin/ExtraccionController.php`: Reescrito — `extract()` ahora usa pipeline internamente, nuevo `extractPipeline()` con SSE streaming.
- `mi3/backend/routes/api.php`: Nueva ruta `POST compras/extract-pipeline`.
- `mi3/frontend/components/admin/compras/ExtractionPipeline.tsx`: Nuevo — componente visual 3 pasos con SSE via ReadableStream, badges labels, tipo detectado, resultado final. Mobile-first, aria-live.
- `mi3/frontend/components/admin/compras/ImageUploader.tsx`: Integrado con ExtractionPipeline visual.

**Commits:** `104dc65`
**Deploys:** mi3-frontend ✅, mi3-backend ✅

### 2026-04-17f — Spec pedidosya-cash-flow: flujo completo PedidosYA Efectivo en caja3

**Cambios:**
- `caja3/src/components/CheckoutApp.jsx`: Modal de selección PedidosYA (Online/Efectivo), nueva función `handlePedidosYACashPayment` con `payment_method: 'pedidosya_cash'`.
- `caja3/src/components/MenuApp.jsx`: Modal Online/Efectivo en checkout inline (el flujo principal de caja), estado `showPedidosYAModal`, redirect map con `pedidosya_cash`.
- `caja3/src/components/MiniComandas.jsx`: Cash Modal inline para confirmar pagos pedidosya_cash (monto exacto, botones rápidos $5K/$10K/$20K, cálculo de vuelto, Enter key), etiqueta "PedidosYA Efectivo" con ícono Banknote.
- `caja3/api/confirm_transfer_payment.php`: Extendido para registrar ingreso en `caja_movimientos` para `pedidosya_cash` con motivo "Venta PedidosYA Efectivo - Pedido #X".
- `caja3/api/get_sales_summary.php`: Agregada categoría `pedidosya_cash` al array de resultados.
- `caja3/src/components/ArqueoApp.jsx`: Tarjeta "PedidosYA Efectivo" con estilo amber, renombrada tarjeta existente a "PedidosYA Online".
- `caja3/src/components/VentasDetalle.jsx`: Badge "PYA Efectivo" (yellow) y filtro `pedidosya_cash`.
- `caja3/sql/add_pedidosya_cash_enum.sql`: ALTER TABLE para agregar `pedidosya_cash` al ENUM de `payment_method`.
- BD: Migración ejecutada en producción — ENUM actualizado.
- BD: Columna `pedidosya_price` agregada a `products`. 20 productos con precios PYA cargados (hamburguesas, completos, sándwiches, papas, bebidas).
- `caja3/src/components/MenuApp.jsx`: `cartSubtotalPYA` calcula total con precios PYA, modal muestra ambos precios, orden usa monto PYA para `pedidosya_cash`. Subtotal y total visibles cambian al seleccionar Efectivo, badge "🛵 Precio PedidosYA Efectivo aplicado" con precio caja tachado. Precios PYA por item en naranja con precio caja strikethrough. Fix condición tipo string/number.

- `caja3/api/get_menu_products.php`: Agregado `pedidosya_price` al SELECT y al array de respuesta — root cause de precios PYA no apareciendo (la API no enviaba el campo).

**Commits:** `a752094`, `40106b6`, `b23f03d`, `1e213d9`, `4fcef99`, `e0050d4`, `93a9c5c`, `9025e58`
**Deploys:** caja3 ✅ (`9025e58`), SQL migrations ✅

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

---

> Sesiones anteriores (165 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
