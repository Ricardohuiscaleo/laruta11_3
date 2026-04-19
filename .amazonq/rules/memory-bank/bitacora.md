# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-19)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`632d7f4`) |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`7e5ea66`) — ingredient categories: tabs dinámicos, API con categorías |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`0edcde8`) — Pipeline multi-agente 4 fases + ReconciliationQuestions UI |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`0edcde8`) — Pipeline multi-agente + FeedbackService auto-aprendizaje |
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

### 2026-04-19e — Spec multi-agent-compras-pipeline: implementación completa tareas 2.6-10

**Cambios:**
- `mi3/backend/app/Services/Compra/FeedbackService.php`: Nuevo — motor auto-aprendizaje con capturarFeedback(), getFewShotExamples(), formatearEjemplos(), computeDiff(). Captura diff entre datos extraídos y guardados, inyecta correcciones como few-shot en futuras extracciones.
- `mi3/backend/app/Services/Compra/PipelineExtraccionService.php`: ejecutarMultiAgente() orquestando 4 agentes (Visión→Análisis→Validación→Reconciliación) con SSE, degradación graceful (agentes 3-4 opcionales), logging tokens por agente, costo USD, env flag `MULTI_AGENT_PIPELINE=true`. FeedbackService inyectado en constructor.
- `mi3/backend/app/Http/Controllers/Admin/CompraController.php`: FeedbackService inyectado, captura feedback al guardar compra si extraction_log_id presente (try/catch no-blocking).
- `mi3/frontend/components/admin/compras/ExtractionPipeline.tsx`: 4 fases multi-agente (Eye/Brain/ShieldCheck/Scale), detección engine "multi-agent", PhaseDetails para validación (inconsistencias) y reconciliación (correcciones auto + preguntas).
- `mi3/frontend/components/admin/compras/ReconciliationQuestions.tsx`: Nuevo — UI preguntas reconciliación con cards, radio buttons, responsive 320px+.
- `mi3/frontend/app/admin/compras/registro/page.tsx`: Flujo reconciliación integrado (onReconciliationNeeded → POST respuestas → aplicar correcciones).
- Tests PBT (8 archivos, 37+ tests, 25K+ assertions): ImageProcessedOnce, VisionOutputStructure, FewShotInjection, FeedbackDiff, ValidationArithmetic, ValidationFiscal, ReconciliationPassThrough, SSEOrder.

**Commits:** `0edcde8`
**Deploys:** mi3-backend ✅ (`0edcde8`), mi3-frontend ✅ (`0edcde8`)
**Pendiente:** Ejecutar `php artisan migrate` en producción (tabla extraction_feedback). Test en vivo con imagen real.

### 2026-04-19d — mi3 StockDashboard: 4 grupos categorías + fix sticky header

**Cambios:**
- `mi3/frontend/components/admin/compras/StockDashboard.tsx`: Reemplazados 2 tabs (Ingredientes/Bebidas) por 4 grupos lógicos: Ingredientes (Carnes, Vegetales, Salsas, Condimentos, Lácteos, Panes, Embutidos, Pre-elaborados), Insumos (Packaging, Limpieza), Bebidas, Operacional (Gas, Servicios). Cada grupo muestra conteo de items.
- `mi3/frontend/components/admin/sections/ComprasSection.tsx`: Fix sticky header gap — `-mt-6 pt-6` para que el header pegue al top sin espacio.
- BD: Fix double-encoded UTF-8 "Lácteos" con `CONVERT(CAST(CONVERT(category USING latin1) AS BINARY) USING utf8mb4)`.

**Commits:** `4a75e68`
**Deploys:** mi3-frontend ✅ (`4a75e68`)

### 2026-04-19c — Spec ingredient-categories-improvement: tabs dinámicos, IA categoría, validación

**Cambios:**
- `mi3/backend/app/Enums/IngredientCategory.php`: Nuevo — constante VALID_CATEGORIES (13 categorías), isValid(), all().
- `mi3/backend/app/Http/Controllers/Admin/StockController.php`: Validación de categoría con Rule::in(VALID_CATEGORIES).
- `mi3/backend/app/Services/Compra/CompraService.php`: crearIngrediente() valida categoría, inválidas → null.
- `mi3/backend/app/Services/Compra/GeminiService.php`: campo `categoria_sugerida` en schema extracción + instrucción en 6 prompts para inferir categoría por IA.
- `caja3/api/compras/get_items_compra.php`: Respuesta cambia de array plano a `{ items, categories, valid_categories }`.
- `caja3/src/components/ComprasApp.jsx`: Tabs dinámicos por categoría (scroll horizontal), tab "Todos" + "Bebidas" + categorías dinámicas desde API. Filtrado por categoría seleccionada.
- `caja3/sql/fix_ingredient_categories.sql`: Fix encoding "LÃ¡cteos"→"Lácteos", limpiar categoría legacy "Ingredientes"→NULL.
- Tests: 5 archivos PBT — IngredientCategoryTest (5 tests), IngredientCategoryPropertyTest (5 tests Eris), CompraServiceCategoryTest (4 tests Eris), CategoryExtractionPropertyTest (4 tests Eris), category-filter.test.mjs (600 iteraciones JS).
- Spec completo: requirements.md (6 reqs), design.md (5 propiedades correctitud), tasks.md (8 tareas + 4 opcionales).

**Commits:** `f31cfe7`, `7e5ea66`
**Deploys:** caja3 ✅ (`7e5ea66`), mi3-backend ✅ (`7e5ea66`)
**Pendiente:** ~~Ejecutar `caja3/sql/fix_ingredient_categories.sql` en BD~~ EJECUTADO. Fix adicional: double-encoded UTF-8 corregido con `CONVERT(CAST(CONVERT(category USING latin1) AS BINARY) USING utf8mb4)`. Verificado via API: 13 categorías limpias, sin caracteres corruptos.

### 2026-04-19b — Fixes post-deploy Gemini: equivalencias, fecha, sidebar, presupuesto IA, crear ingrediente inline

**Cambios:**
- `mi3/backend/app/Services/Compra/GeminiService.php`: Fix google_api_key lowercase fallback para Coolify env. Prompts producto/bascula: "NO uses fechas de vencimiento o fabricación".
- `mi3/backend/app/Services/Compra/PipelineExtraccionService.php`: normalizeFecha() fallback a hoy si fecha null/inválida/empaque. reconcileSingleItemTotal() ajusta subtotal item único al monto_total boleta. applyProductEquivalences() skip cuando item ya viene en unidad base (kg/kilos/g/unidad/litro/ml). Base units expandida con variantes (kilos, litros, gramos).
- `mi3/backend/app/Services/Loan/LoanService.php`: Adelanto de sueldo se registra como -abs() (descuento, no abono).
- `mi3/backend/app/Http/Controllers/Admin/ExtraccionController.php`: Nuevo endpoint `GET ai-budget` con tokens, costo USD, saldo CLP.
- `mi3/backend/routes/api.php`: Ruta `compras/ai-budget`.
- `mi3/frontend/components/admin/sections/ComprasSection.tsx`: Sticky header con presupuesto IA (saldo/total + barra progreso + tokens + usos).
- `mi3/frontend/components/admin/AdminShell.tsx`: Sidebar fija desktop (sticky top-0 h-screen).
- `mi3/frontend/components/admin/AdminSidebarSPA.tsx`: h-full para sidebar.
- `mi3/frontend/app/admin/compras/registro/page.tsx`: Botón "✨ Crear como ingrediente" cuando match < 75%. Tag "🆕 Nuevo" post-creación.
- `mi3/frontend/app/admin/compras/consola/page.tsx`: Fases dinámicas Gemini/Bedrock + tokens/costo USD.
- BD: Compra #281 fecha corregida 2023→2026. Ajuste #38 Andrés: +50000→-50000.

**Commits:** `2de4203`→`2ce0240` (13 commits)
**Deploys:** mi3-frontend ✅ (`2ce0240`), mi3-backend ✅ (`f91112d`)

---

> Sesiones anteriores (170+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
