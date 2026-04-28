# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-28)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`d880e70`) — delivery config centralizado BD, card_surcharge separado |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`b01b344`) — delivery config centralizado BD, card_surcharge separado, MenuApp+CheckoutApp migrados, fix api/delivery/ gitignore |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`d880e70`) — DeliveryConfigSection admin, sección Config Delivery en sidebar |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`d880e70`) — delivery_config table, card_surcharge column, API CRUD delivery-config |
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
| mi3-backend | `php artisan schedule:run` (11 comandos) | `* * * * *` |
| mi3-backend | `delivery:generate-daily-settlement` | `23:59` diario |
| mi3-backend | `delivery:check-pending-settlements` | `12:00` diario |
| mi3-backend | `mi3:cierre-diario` | `04:15` diario (Chile) |
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

- [x] **🚨 Ejecutar SQL `dispatch_photo_feedback` en BD** — Tabla creada en producción via docker exec.
- [x] **🚨 Revertir descuento 10% temporal** — 4 productos revertidos manualmente (cron VPS no se ejecutó). is_featured=0, sale_price=NULL. Crons eliminados del VPS. Scripts apply/revert pendientes de eliminar del repo.
- [ ] **🚨 URGENTE: Rotar AWS access key comprometida** — AWS detectó key `AKIAUQ24...WGTE` como comprometida y restringió servicios (Bedrock bloqueado). Key rotada a `...RKT7` en Coolify. Falta: 1) Actualizar `~/.aws/credentials` en VPS para chef-bot, 2) Desactivar key vieja en IAM, 3) Responder caso de soporte AWS (caso #177655445900588 respondido, esperando humano). Bedrock sigue bloqueado a nivel de cuenta.
- [x] **🚨 CRÍTICO: Inventario no descuenta para R11/R11C/combos** — COMPLETADO. Guard idempotencia, order_status=sent_to_kitchen, callbacks preservan order_status, create_order centralizado, subtotal/delivery_fee server-side, backfill expandido, fix combos usa fixed_items JSON. Commits `bdee29d`, `98c5565`. Backfill: 218 órdenes procesadas, 0 errores.
- [x] **Implementar Gemini como proveedor IA principal** — GeminiService.php creado con Structured Outputs, pipeline 2 fases (clasificación + análisis), token tracking, frontend v1.7. Commit `009259d`. Falta: test en vivo con imagen real.

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

- [x] **Pipeline multi-agente compras (optimización costos Gemini)** — COMPLETADO. 4 agentes (Visión→Análisis→Validación→Reconciliación), FeedbackService auto-aprendizaje, frontend 4 fases SSE, ReconciliationQuestions UI, 8 property tests (25K+ assertions). Commit `0edcde8`. Pendiente: ejecutar migración `extraction_feedback` en BD, test en vivo con imagen real.
- [x] **Crear ingrediente smart con categoría inferida** — Cuando se crea ingrediente inline desde compras, inferir categoría automáticamente del contexto (insumos si es envase, ingredientes si es alimento, etc.).
- [x] **Refactorizar categorías de ingredientes** — Fix encoding "LÃ¡cteos"→"Lácteos", eliminar categoría vacía "Ingredientes" (0 items), Stock frontend debe mostrar todas las 14 categorías (hoy solo muestra Ingredientes y Bebidas), considerar tabla separada `ingredient_categories` en vez de string libre. Categorías actuales: Carnes(10), Vegetales(20), Salsas(8), Condimentos(8), Panes(4), Embutidos(1), Pre-elaborados(1), Lácteos(4), Bebidas(7), Gas(2), Servicios(4), Packaging(28), Limpieza(15).

- [x] Obtener chat_id del grupo "Pedidos 11" — no aplica, flujo directo al bot de Telegram configurado.
- [x] **Ejecutar migraciones `checklists_v2`** — obsoleto, sistema de checklists reescrito en mi3.
- [x] **Limpiar datos de prueba delivery** — eliminados 6 pedidos TEST-DLV-* y SIM-*. Pendiente: revertir roles rider de Camila(1), Andrés(3), Dafne(18) cuando termine el testing.
- [x] Recalcular delivery\_fee server-side en `create_order.php` — COMPLETADO en spec fix-inventario-ventas-comandas Task 5.3. Commit `bdee29d`.
- [x] **Migrar prompts IA a BD** — PARCIAL. Tablas creadas, 17 prompts seeded, API CRUD funcional, UI PromptsManager en Consola. PERO: GeminiService restaurado a versión hardcoded porque el refactor eliminó métodos públicos (percibir, analizar, validar, reconciliar). Pendiente: re-hacer tarea 8 del spec sin eliminar métodos de API call.
- [ ] **Migrar tracking público de app3 a mi3** — `app3/src/pages/tracking/` usa polling HTTP, debería estar en mi3-frontend con Reverb WebSocket nativo para realtime real. Actualmente embebido via iframe en payment-success. Además: ocultar informe técnico al usuario, mostrar tracking en pedidos pending (no solo payment-success), integrar en MiniComandasCliente de app3. No necesario en caja3.
- [x] **Integrar checklists mi3 en caja3** — COMPLETADO. Public/ChecklistController.php con 5 endpoints, ChecklistApp.jsx reescrito para consumir mi3 API. Commit `eaceaab`.
- [x] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143) — CheckoutApp.jsx corregido de 0.6→0.7143, delivery_discount ahora se envía en todos los payloads de CheckoutApp y MenuApp.
- [x] **Fix ShiftService turnos dinámicos + reemplazos** — `generate4x4Shifts()` ahora trackea `reemplazo_seguridad` + `reemplazado_por` para seguridad. Eliminado `monto_reemplazo` falso en dinámicos. Commit `af6e236`.
- [x] **Verificar Google Maps en mi3-frontend** — mapId `d51ca892b68e9c5e5e2dd701` + API key funcionando ✅
- [x] **Deploy spec delivery-tracking-realtime** — commit `70650cf` pusheado. Builds disparados en Coolify. Pendiente verificar builds y ejecutar `php artisan migrate`.
- [x] **Integración caja3/app3 delivery** — webhook en caja3 y iframe en app3 implementados en commit `70650cf`.
- [ ] **Investigar arquitectura SaaS multi-tenant** — AWS Lambda + Aurora PostgreSQL + Amazon Location Service + Stripe. Dominio candidato: pocos.click (caduca 2026-12-21)
- [x] **Completar prep_data 54 ingredientes faltantes** — COMPLETADO. Churrasco, Cordero, Lomo Cerdo, Tocino Laminado, Tomahawk, Pre-pizza, Champiñón, Pan Completo XL, Mayonesa, etc. Todos asignados via tinker en BD.
- [x] **Spec sub-recetas-hamburguesas** — COMPLETADO. Tabla `ingredient_recipes`, flag `is_composite`, API CRUD, UI sub-tab en Recetas con editor y calculadora de producción. Carne Molida creada, Tocino stock corregido, sub-receta Hamburguesa R11 seeded. Commits `09f5a91`, `5930ec3`.
- [x] **Spec recetas-fix-integral** — COMPLETADO. Fix tocino 7 recetas, recalcular 50 cost_price, editor inline recetas, stock deduction compuestos en caja3, label costo/unidad. Commit `0034f3a`.

---

## Sesiones Recientes

### 2026-04-28b — Spec delivery-config-centralized: centralizar config delivery en BD

**Cambios código:**
- `mi3/backend/database/migrations/`: Tabla `delivery_config` (key-value, 6 params seed) + columna `tuu_orders.card_surcharge`.
- `mi3/backend/app/`: DeliveryConfig model, DeliveryConfigController (GET/PUT), UpdateDeliveryConfigRequest (validación numérica + rango rl6).
- `mi3/backend/routes/api.php`: Rutas delivery-config en grupo admin.
- `mi3/frontend/`: DeliveryConfigSection.tsx (formulario, validación inline, vista previa cálculo), registrada en AdminShell + sidebars.
- `app3/api/delivery/`: delivery_config_helper.php + get_config.php (endpoint público con fallback).
- `caja3/api/delivery/`: Copia idéntica helper + endpoint.
- `app3/api/location/get_delivery_fee.php`: Lee distance params de BD.
- `app3/api/create_order.php` + `caja3/api/create_order.php`: card_surcharge separado, no suma a delivery_fee.
- `app3/src/components/CheckoutApp.jsx`: Fetch config on mount, valores dinámicos.
- `caja3/src/components/CheckoutApp.jsx` + `MenuApp.jsx`: Idem, usa `(1 - factor)` para RL6.

**BD pendiente:** ~~Ejecutar `php artisan migrate` para crear tabla `delivery_config` y columna `tuu_orders.card_surcharge`.~~ EJECUTADO manualmente via docker exec MySQL.

**Commits:** `d880e70` (22 archivos, 1701 insertions), `b01b344` (fix: force-add caja3/api/delivery/ ignorado por root .gitignore)
**Deploys:** mi3-frontend ✅, mi3-backend ✅, app3 ✅, caja3 ✅ (redeploy fix `b01b344`)
**BD:** `tuu_orders.card_surcharge` DECIMAL(10,2) creada, tabla `delivery_config` con 6 registros seeded

### 2026-04-28a — Spec dispatch-photo-verification: verificación fotos delivery con Gemini IA

**Cambios código:**
- `caja3/src/utils/photoRequirements.js`: Nueva función pura `generatePhotoRequirements(deliveryType)` + helpers `getButtonState`, `formatPhotoProgress`.
- `caja3/api/GeminiService.php`: Prompts inteligentes — recipe_description priorizado, clasificación dinámica ingredientes por categoría BD (visible/no visible/packaging), verificación orientación envases en bolsa, sugerencias de corrección específicas, token tracking con `_tokens` + `_processing_ms`.
- `caja3/api/orders/save_dispatch_photo.php`: INSERT con `ai_tokens_total`, `ai_model`, `processing_time_ms`. Fallback también con columnas nuevas.
- `caja3/src/components/MiniComandas.jsx`: Delivery → 2 slots etiquetados (productos + bolsa sellada) en grid-cols-2, flujo 2 fases: "📦 DESPACHAR A DELIVERY" (fotos + status→ready) → "✅ ENTREGAR" (status→delivered). Botón despacho visible siempre en delivery (independiente de isPaid). `dispatchToDelivery()` nueva función. UX overhaul: Lucide icons (Trash2, ShieldCheck, ShieldAlert, Loader2, ImagePlus), shimmer loader moderno, bloqueo subida durante análisis IA, timeout 40s con fallback, slots compactos h-28, feedback inline coloreado. Local → sin fotos, botón "✅ ENTREGAR" sin cambios.
- `app3/api/tuu/get_comandas_v2.php`: Agrega `i.category` al SELECT de recipe ingredients + `recipe_ingredients` array con categoría para clasificación IA.
- `caja3/create_dispatch_photo_feedback.sql`: Nueva tabla con order_id, photo_type, ai_aprobado, ai_puntaje, ai_feedback, user_retook.

**BD:** Tabla `dispatch_photo_feedback` creada + columnas `ai_tokens_total`, `ai_model`, `processing_time_ms` agregadas. Fix: `tuu_orders.dispatch_photo_url` cambiado de `varchar(500)` a `TEXT` (truncaba JSON con múltiples URLs).

**Commits:** `6201bd8`→`493dd52` (9 commits)
**Deploys:** caja3 ✅ (`493dd52`), app3 ✅ (`493dd52`)

### 2026-04-27h — Spec caja3-inline-merma-arqueo: paneles inline + rediseño UX completo

**Cambios código:**
- `caja3/src/components/ChecklistApp.jsx`: Prop `rol` dinámico (cajero/planchero).
- `caja3/src/pages/checklist-planchero.astro`: Nueva página checklist planchero.
- `caja3/src/pages/comandas/index.astro`: Fix link checklist.
- `caja3/src/components/MenuApp.jsx`: Navbar unificada `#1a1a1a`, títulos producto `text-sm`, Agregar inline, `openPanel`/`closePanel` con params + lazy loading (MermaPanel, ArqueoPanel, VentasDetalle).
- `caja3/src/components/MermaPanel.jsx`: Rediseño completo — header gradiente rojo→naranja, 3 pasos (buscar con highlight amarillo → motivos grid 3x4 emojis → resumen), cantidad/unidad inline, excluye extras/personalizar, solo búsqueda (sin listado), stock oculto en productos.
- `caja3/src/components/ArqueoPanel.jsx`: Header gradiente rojo→naranja, X blanco, `openPanel` prop para VentasDetalle inline.
- `caja3/src/components/VentasDetalle.jsx`: Acepta props `startDate`/`endDate`/`onClose`, header gradiente, funciona como panel inline desde ArqueoPanel.

- `caja3/src/components/MiniComandas.jsx`: Compacto — padding 1px, nombre abreviado (Ricardo H.), switch List/LayoutGrid (Lucide), modo listado default con fotos thumbnail+click-to-zoom, detalle combos expandido, nombre `text-sm` con precio abajo.

**Commits:** `204fffb`→`a8ac18a` (16 commits)
**Deploys:** caja3 ✅ (`a8ac18a`)

### 2026-04-27g — Backfill combo ingredients históricos

**Cambios código:**
- `app3/api/backfill_combo_ingredients.php`: Nuevo script — identifica combos con solo product-level transactions (sin ingredient expansion), elimina txs viejas, revierte stock_quantity, y re-procesa con processSaleInventory que expande fixed_items + selections.

**BD:** 26 combos históricos corregidos (Combo Dupla, Combo Completo, Combo Gorda, etc.). Transactions product-level reemplazadas por ingredient-level. Incluye R11C-1777244854-5529 (Combo Dupla).

**Commits:** `014f44e`, `9b2eaa6`
**Deploys:** app3 ✅ (`9b2eaa6`)

### 2026-04-27f — MiniComandas muestra R11 Webpay + delivery_discount en ventas-detalle + payment-success RL6

**Cambios código:**
- `caja3/src/components/MiniComandas.jsx`: Eliminado filtro que excluía TODAS las R11-* de activeOrders. Bug raíz: `!(o.order_number.startsWith('R11-'))` impedía que órdenes Webpay aparecieran en comandas.
- `caja3/api/get_sales_detail.php`: Agregados `delivery_discount` y `subtotal` al SELECT.
- `caja3/src/components/VentasDetalle.jsx`: Badge verde con descuento RL6 en delivery fee.
- `app3/src/pages/payment-success.astro`: Muestra descuento RL6 en delivery (`$3.500 (-$1.000 desc. RL6 = $2.500)`).

**BD:** Orden R11-1777252234-7988 cambiada a sent_to_kitchen para testing.

**Commits:** `5ffc205`, `3c17c68`, `f214140`, `5e9e739`, `5b0865f`, `098a4d2`
**Deploys:** app3 ✅ (`5b0865f`), caja3 ✅ (`098a4d2`)

### 2026-04-27e — Fix payment-success "Cargando..." + limpieza disco

**Cambios código:**
- `app3/api/tuu/get_order_products_with_extras.php`: Reemplazadas credenciales hardcodeadas de Hostinger por `$config` (causaba "Database connection failed" en Docker). Agregado `combo_data` al SELECT para que payment-success muestre componentes de combos.
- `app3/api/tuu/get_order_delivery.php`: Agregados campos `product_price`, `subtotal`, `delivery_discount`, `scheduled_time`, `is_scheduled` al SELECT.
- `app3/src/pages/payment-success.astro`: Total se carga desde BD via `product_price` cuando no viene en URL params.

**Commits:** `e96eedd`
**Deploys:** app3 ✅ (`e96eedd`)

---

> Sesiones anteriores (170+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-27e archivadas. Últimas archivadas: 2026-04-27e (Fix payment-success "Cargando..." + limpieza disco), 2026-04-27f (MiniComandas R11 Webpay + delivery_discount), 2026-04-27g (Backfill combo ingredients), 2026-04-27d (Fix selections agrupadas en combos).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
