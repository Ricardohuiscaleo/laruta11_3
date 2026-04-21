# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-21)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`13eecde`) — crédito R11 completo: r11c-pending page, 10% desc, refund cancel |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`3231e67`) — R11C visible en MiniComandas, r11_refund_credit.php |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`596945c`) — recetas: secciones producto/ingredientes/insumos, editar nombre/desc/foto |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`596945c`) — getRecipeDetail devuelve category de ingrediente |
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

- [ ] **🚨 URGENTE: Rotar AWS access key comprometida** — AWS detectó key `AKIAUQ24...WGTE` como comprometida y restringió servicios (Bedrock bloqueado). Key rotada a `...RKT7` en Coolify. Falta: 1) Actualizar `~/.aws/credentials` en VPS para chef-bot, 2) Desactivar key vieja en IAM, 3) Responder caso de soporte AWS (caso #177655445900588 respondido, esperando humano). Bedrock sigue bloqueado a nivel de cuenta.
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
- [ ] Recalcular delivery\_fee server-side en `create_order.php`
- [x] **Migrar prompts IA a BD** — PARCIAL. Tablas creadas, 17 prompts seeded, API CRUD funcional, UI PromptsManager en Consola. PERO: GeminiService restaurado a versión hardcoded porque el refactor eliminó métodos públicos (percibir, analizar, validar, reconciliar). Pendiente: re-hacer tarea 8 del spec sin eliminar métodos de API call.
- [ ] **Migrar tracking público de app3 a mi3** — `app3/src/pages/tracking/` usa polling HTTP, debería estar en mi3-frontend con Reverb WebSocket nativo para realtime real. Actualmente embebido via iframe en payment-success. Además: ocultar informe técnico al usuario, mostrar tracking en pedidos pending (no solo payment-success), integrar en MiniComandasCliente de app3. No necesario en caja3.
- [x] **Integrar checklists mi3 en caja3** — COMPLETADO. Public/ChecklistController.php con 5 endpoints, ChecklistApp.jsx reescrito para consumir mi3 API. Commit `eaceaab`.
- [x] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143) — CheckoutApp.jsx corregido de 0.6→0.7143, delivery_discount ahora se envía en todos los payloads de CheckoutApp y MenuApp.
- [x] **Fix ShiftService turnos dinámicos + reemplazos** — `generate4x4Shifts()` ahora trackea `reemplazo_seguridad` + `reemplazado_por` para seguridad. Eliminado `monto_reemplazo` falso en dinámicos. Commit `af6e236`.
- [x] **Verificar Google Maps en mi3-frontend** — mapId `d51ca892b68e9c5e5e2dd701` + API key funcionando ✅
- [x] **Deploy spec delivery-tracking-realtime** — commit `70650cf` pusheado. Builds disparados en Coolify. Pendiente verificar builds y ejecutar `php artisan migrate`.
- [x] **Integración caja3/app3 delivery** — webhook en caja3 y iframe en app3 implementados en commit `70650cf`.
- [ ] **Investigar arquitectura SaaS multi-tenant** — AWS Lambda + Aurora PostgreSQL + Amazon Location Service + Stripe. Dominio candidato: pocos.click (caduca 2026-12-21)
- [x] **Spec sub-recetas-hamburguesas** — COMPLETADO. Tabla `ingredient_recipes`, flag `is_composite`, API CRUD, UI sub-tab en Recetas con editor y calculadora de producción. Carne Molida creada, Tocino stock corregido, sub-receta Hamburguesa R11 seeded. Commits `09f5a91`, `5930ec3`.
- [x] **Spec recetas-fix-integral** — COMPLETADO. Fix tocino 7 recetas, recalcular 50 cost_price, editor inline recetas, stock deduction compuestos en caja3, label costo/unidad. Commit `0034f3a`.

---

## Sesiones Recientes

### 2026-04-21b — Recetas: secciones producto/ingredientes/insumos, editar nombre/desc/foto

**Cambios:**
- `mi3/backend/app/Services/Recipe/RecipeService.php`: `getRecipeDetail()` ahora devuelve `category` de cada ingrediente para separar ingredientes de insumos en frontend.
- `mi3/frontend/app/admin/recetas/page.tsx`: Reescrito RecipeEditor con 3 secciones: **Producto** (editar nombre, descripción para IA, subir/cambiar foto), **Ingredientes** (Carnes, Vegetales, Salsas, etc.), **Insumos** (Packaging, Limpieza, Gas, Servicios). Buscador autocomplete mejorado con safety check `Array.isArray`, filtro por tipo, categoría visible en resultados. Resumen de costos desglosado ingredientes + insumos.

**Commits:** `596945c`
**Deploys:** mi3-frontend ✅ (`596945c`), mi3-backend ✅ (`596945c`)

### 2026-04-21a — Fix crédito R11: prefijo R11C, payment_status, inventario, badge comandas

**Cambios:**
- `app3/api/create_order.php`: Prefijo `R11C-` para órdenes con `r11_credit` (antes `T11-`). `payment_status = 'paid'` automático (antes `unpaid`). Descuento de inventario para `r11_credit` (antes solo `rl6_credit`). Logs dinámicos con `$payment_method`.
- `app3/src/components/CheckoutApp.jsx`: Redirect a `/payment-success?order=...&method=r11_credit` (antes `/r11-pending` que no existía).
- `app3/src/pages/payment-success.astro`: Método de pago dinámico — detecta param `method` y muestra label correcto (🏪 Crédito R11, 🎖️ Crédito RL6, etc).
- `app3/src/pages/comandas/index.astro`: Badge `🏪 R11` verde esmeralda para órdenes `R11C-`.
- `app3/api/r11/get_credit.php`: Removida auth session_token innecesaria (consistente con RL6 get_credit).
- `app3/src/components/modals/ProfileModalModern.jsx`: Tabs padding `px-4`→`px-1`, font `extrabold`→`semibold`, tab R11 texto "Crédito" en verde esmeralda.
- `app3/src/components/MenuApp.jsx`: Eliminado sistema tracking (track_usage.php).
- `app3/api/track_usage.php`: Eliminado.

**Commits:** `05d6b0a`, `ed1f0ff`, `825708a`, `8931c75`, `4dfd571`, `9b17c05`, `4de0f6e`, `ae5c180`
**Deploys:** app3 ✅ (`ae5c180`), caja3 ✅ (`9b17c05`)
**BD:** Ricardo (id=4) `credito_r11_aprobado = 1`, `limite_credito_r11 = 50000`, `fecha_aprobacion_r11 = 2026-04-21`. RL6 deshabilitado (`es_militar_rl6 = 0`). `relacion_r11 = 'Administrador'`. Fix orden R11C-1776792155-8230: `pagado_con_credito_r11 = 1`, `credito_r11_usado = 7362`.

### 2026-04-20f — Reemplazo masivo ingredientes + MobileExtractionSheet fix + pipeline race condition

**Cambios:**
- `mi3/frontend/components/admin/compras/ExtractionPipeline.tsx`: Fix race condition — `initPhasesForEngine` + `updatePhase` ahora atómicos en un solo `setPhases`. Check badge `h-2.5 w-2.5` en `-top-1.5 -right-1.5`.
- `mi3/frontend/components/admin/compras/MobileExtractionSheet.tsx`: Check badge minimalista (icono fase siempre visible, badge `h-3.5 w-3.5` en esquina). Backdrop `onClick={onClose}`. Botón "Listo" clickeable para cerrar.
- `mi3/frontend/app/admin/recetas/ajuste-masivo/page.tsx`: Reescrito — de "Ajuste Masivo de Costos" a "Reemplazo Masivo de Ingredientes" con autocomplete picker, preview tabla productos afectados, detección duplicados, feedback confirmación.
- `mi3/frontend/components/admin/sections/RecetasSection.tsx`: Tab "Ajuste Masivo" → "Reemplazo" (icono Replace).
- `mi3/backend/app/Services/Recipe/RecipeService.php`: `replaceIngredientPreview()` + `replaceIngredientApply()` — swap atómico en transacción + recalcula cost_price.
- `mi3/backend/app/Http/Controllers/RecipeController.php`: 2 endpoints replace-ingredient + catch-all exception handler.
- `mi3/backend/routes/api.php`: 2 rutas replace-ingredient.
- `mi3/backend/app/Models/ProductRecipe.php`: `timestamps = false` — tabla no tiene created_at/updated_at.
- `mi3/backend/app/Services/Compra/CompraService.php`: Cascade al registrar compra — `cascadeCompositeCosts()` recalcula padres compuestos.
- `mi3/frontend/app/admin/compras/registro/page.tsx`: Smart search ingredientes — al editar nombre se desvincula match, búsqueda en vivo, crear ingrediente inline con selector de categoría.
- `mi3/backend/app/Services/Compra/GeminiService.php`: Fix RUT Unimarc (600→500), equivalencias packaging (PAN COMPLETO XL 1 bolsa=6 un, PAN HAMBURGUESA 1 bolsa=4 un).
- `mi3/backend/app/Services/Compra/PipelineExtraccionService.php`: knownPatterns: rendic→Unimarc, unimarc→Unimarc, arauco→Arauco.

**Commits:** `5b8ed85`, `b860495`, `f3816c3`, `1fa5f65`, `ce3a50a`, `3419f96`, `2e85939`, `d162556`, `138ca32`, `1873b95`
**Deploys:** mi3-frontend ✅ (`2e85939`), mi3-backend ✅ (`1873b95`)
**BD:** Fix compras_detalle #497: ingrediente_id 163→49 (Tocino registrado como Carne Molida). Carne Molida stock 5.38→4.52 kg, precio $14,000→$6,490/kg. Tocino stock 3.92→4.78 kg. cascadeCompositeCosts: Hamburguesa R11 $1,775.75→$1,613.40, 12 productos recalculados.

### 2026-04-20d — Spec recetas-fix-integral: implementación completa

**Cambios:**
- BD: UPDATE tocino en 7 product_recipes (unidad→kg), recalcular cost_price de 50 productos, precios tocino $14,000/kg y longaniza $15,980/kg.
- `mi3/frontend/app/admin/recetas/page.tsx`: Refactorizado — editor inline con `selectedProductId` en vez de `router.push`. Autocomplete, tabla ingredientes, save POST/PUT.
- `mi3/frontend/app/admin/recetas/sub-recetas/page.tsx`: Label costo con unidad real (`$14.000/kg`).
- `caja3/api/confirm_transfer_payment.php`: `deductProduct()` ahora detecta `is_composite` y descompone en hijos via `resolveIngredientDeduction()`.
- `caja3/api/process_sale_inventory.php`: Misma lógica de descomposición con `resolveIngredientDeductionPSI()`.

**Commits:** `0034f3a`, `7eaa539` (fix unit conversion)
**Deploys:** mi3-frontend ✅, mi3-backend ✅, caja3 ✅
**BD:** Tocino fix 7 recetas, 50 cost_price recalculados, precios ingredientes actualizados.

### 2026-04-20c — Spec sub-recetas-hamburguesas: implementación completa

**Cambios:**
- `mi3/backend/database/migrations/2026_04_20_300000_create_ingredient_recipes_table.php`: Tabla `ingredient_recipes` + columna `is_composite` en ingredients + seed Carne Molida + fix stock Tocino + sub-receta Hamburguesa R11.
- `mi3/backend/app/Models/IngredientRecipe.php`: Nuevo model con relaciones parent/child.
- `mi3/backend/app/Models/Ingredient.php`: Agregado `is_composite` a fillable/casts + relaciones `subRecipeItems()`, `parentRecipes()`.
- `mi3/backend/app/Services/Recipe/IngredientRecipeService.php`: CRUD + calculateCompositeCost + calculateCompositeStock con unit conversion.
- `mi3/backend/app/Http/Controllers/Admin/IngredientRecipeController.php`: 4 endpoints REST.
- `mi3/backend/routes/api.php`: 4 rutas ingredient-recipes en admin group.
- `mi3/backend/app/Services/Recipe/RecipeService.php`: `calculateRecipeCost()` ahora detecta ingredientes compuestos y calcula costo desde hijos.
- `mi3/frontend/app/admin/recetas/sub-recetas/page.tsx`: UI completa — lista cards, editor con autocomplete, calculadora de producción integrada.
- `mi3/frontend/components/admin/sections/RecetasSection.tsx`: Nueva tab "Sub-Recetas" con icono Layers.

**Commits:** `09f5a91`, `5930ec3` (fix FK type)
**Deploys:** mi3-backend ✅ (`5930ec3`), mi3-frontend ✅ (`09f5a91`)
**BD:** Migración ejecutada — tabla `ingredient_recipes` creada, Carne Molida (id=163) insertada, Tocino stock corregido (58.50→2.93 kg), Hamburguesa R11 marcada compuesta con 3 hijos. Post-deploy: Tocino cost_per_unit corregido $399.59→$14,000/kg, Longaniza $5,000→$15,980/kg. Costo unitario hamburguesa ahora $1,693.30 (coincide con hc.html).

---

> Sesiones anteriores (170+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-20b archivadas. 2026-04-20b (header unificado), 2026-04-20c (sub-recetas-hamburguesas) archivadas.
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
