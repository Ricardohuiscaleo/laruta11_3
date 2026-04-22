# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-22)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`00e32bc`) — UX: rojo sólido, scroll continuo, iconos rojos, auto-scroll barra |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`0723c72`) — pending pages sin WhatsApp, "Volver a Caja" primario |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`f4f134b`) — tab Combos en Recetas, editor inline, autocomplete |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`f4f134b`) — ComboService CRUD, migración combo_components, 4 endpoints REST |
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

### 2026-04-22c — UX app3: scroll continuo, rojo sólido, eliminar hamburguesas 100g

**Cambios:**
- `app3/src/components/MenuApp.jsx`: Selector categorías gradiente→rojo sólido (`bg-red-600`). Eliminada flecha `<>` scroll. Scroll continuo entre categorías con IntersectionObserver + smooth scroll. Fix títulos debajo del header (`scroll-mt-[140px]`, `pt-[140px]`). Helper `getCategoryData()` para renderizar todas las categorías en secuencia.
- BD: `menu_categories` id=2 (Hamburguesas 100g) desactivada. id=1 renombrada "Hamburguesas (200g)" → "Hamburguesas".

**Commits:** `7991e02`, `00e32bc`
**Deploys:** app3 ✅ (`00e32bc`)

### 2026-04-22b — Fix pending pages + uniformizar bebidas combos

**Cambios:**
- `app3/src/pages/transfer-pending.astro`: Eliminado botón WhatsApp + funciones JS asociadas. "Volver al Menú" como botón primario naranja.
- `app3/src/pages/cash-pending.astro`: Mismo fix.
- `app3/src/pages/card-pending.astro`: Mismo fix.
- `app3/src/pages/rl6-pending.astro`: Eliminado WhatsApp + fix TypeScript syntax (`: any`, `: string[]`, `as any`, `window as any`) + fix `urlParams` duplicado.
- `app3/src/pages/r11c-pending.astro`: Agregado combo_data handling (fixed_items + selections con →).
- `caja3/src/pages/cash-pending.astro`: Eliminado botón WhatsApp. "Volver a Caja" como primario.
- `caja3/src/pages/transfer-pending.astro`: Mismo fix.
- `caja3/src/pages/card-pending.astro`: Mismo fix.
- `caja3/src/pages/pedidosya-pending.astro`: Mismo fix.

**Commits:** `0723c72`
**Deploys:** app3 ✅, caja3 ✅ (ambos `0723c72`)
**BD:** Uniformización bebidas combos — 6 combos de lata ahora tienen las mismas 15 opciones 350ml. Doble Mixta 8→15, Completo 11→15, Gorda 11→15, Dupla 8→15, Hamburguesa Clásica 5→15, Salchipapa 4→15. Familiar sin cambios (1.5Lt).

### 2026-04-22a — Spec combos-refactor: Fases 1-4 completas (código + deploy)

**Cambios:**
- `mi3/backend/database/migrations/2026_04_22_100000_create_combo_components_table.php`: Tabla `combo_components` con seed de datos legacy (combo_items→fijos, combo_selections→seleccionables), mapping combos.id→products.id, limpieza product_recipes de combos, fix typo id=233.
- `mi3/backend/app/Models/ComboComponent.php`: Modelo con fillable, casts (is_fixed→boolean, price_adjustment→float), relaciones combo/childProduct.
- `mi3/backend/app/Services/Recipe/ComboService.php`: 5 métodos — getComboList, getComboDetail (filtro is_active=1), saveComboComponents (transacción), calculateComboCost (Σfijos + promedio seleccionables), deleteComboComponents.
- `mi3/backend/app/Http/Controllers/Admin/ComboController.php`: 4 endpoints REST (index, show, store, destroy) con validación estricta y try/catch.
- `mi3/backend/routes/api.php`: 4 rutas admin/combos.
- `mi3/frontend/app/admin/recetas/combos/page.tsx`: Página completa — lista responsive (cards mobile + tabla desktop), editor inline con items fijos + grupos de selección + autocomplete productos, badges 🟢/🔴 disponibilidad, cálculo costo/margen.
- `mi3/frontend/components/admin/sections/RecetasSection.tsx`: Tab "Combos" con icono Package.
- `app3/api/get_combos.php`: Reescrito — JOIN combo_components + products WHERE is_active=1, backward compat combo_id, prepared statements.
- `app3/src/components/modals/ComboModal.jsx`: Eliminado comboMapping hardcodeado, fetch directo con product_id, soporte price_adjustment (+$X / Incluido), sin console.log.
- `caja3/api/get_combos.php`: Sync con app3 (idéntico).
- `caja3/src/components/modals/ComboModal.jsx`: Mismo refactor que app3, preservando UI POS (wider modal, 3-col grid).

**Commits:** `f4f134b`
**Deploys:** mi3-backend ✅, mi3-frontend ✅, app3 ✅, caja3 ✅ (todos `f4f134b`)
**BD:** Migración `combo_components` ejecutada — 62 registros (14 fijos + 48 seleccionables), product_recipes combos = 0, fix typo id=233. Smoke test: Combo Doble Mixta (187) → 2 fijos + 8 bebidas ✅.
**Pendiente:** Fase 5 QA manual.

### 2026-04-21d — Porciones estándar + Creador de recetas con IA (Gemini)

**Cambios:**
- `mi3/backend/database/migrations/2026_04_21_400000_create_portion_standards_table.php`: Tabla `portion_standards` (category_id, ingredient_id, quantity, unit) con 34 porciones seed para Hamburguesas, Sandwiches, Completos, Papas.
- `mi3/backend/app/Models/PortionStandard.php`: Modelo con relaciones category/ingredient.
- `mi3/backend/app/Http/Controllers/Admin/PortionController.php`: CRUD porciones + endpoint `POST suggest-recipe` con IA.
- `mi3/backend/app/Services/Recipe/RecipeAIService.php`: Servicio Gemini 2.0 Flash — genera receta completa usando inventario real, porciones estándar, calcula costos reales, sugiere precio con margen 65%.
- `mi3/backend/routes/api.php`: 4 rutas portions (GET index, GET show, PUT update, POST suggest-recipe).
- `mi3/frontend/app/admin/recetas/porciones/page.tsx`: Tab "Porciones" — vista por categoría, edición inline, costo/unidad.
- `mi3/frontend/app/admin/recetas/creador-ia/page.tsx`: Tab "Creador IA" — input descripción + categoría, genera receta con ingredientes, costos, precio, stock check, tips.
- `mi3/frontend/components/admin/sections/RecetasSection.tsx`: 2 nuevas tabs (Scale, Sparkles).

**Commits:** `2826e66`, `6152f94`, `f03f289`, `e686b12`, `03c919d`
**Deploys:** mi3-frontend ✅ (`03c919d`), mi3-backend ✅ (`03c919d`)
**BD:** Migración `portion_standards` ejecutada, 34 porciones seeded. Fix BD: Pan Churrasco Frica duplicados consolidados (160-162→159), 17 txs Brioche→Frica, 21 recetas actualizadas.

---

> Sesiones anteriores (170+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-21b archivadas. Últimas: 2026-04-21a (fix crédito R11), 2026-04-21b (recetas emojis).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
