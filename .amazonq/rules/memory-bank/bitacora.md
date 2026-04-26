# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-25)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`dce8ea6`) — scripts sale temporal 10% (apply/revert), badge 🔥 OFERTA activo en 4 productos |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`8d024b5`) — fix tiempo negativo comandas, ocultar notas pago en cocina, minicomandas header legible |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`9b63c08`) — bebidas muestra productos reales por subcategoría, recetas accordion por categoría |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`9b63c08`) — BeverageService productos, RecipeService grouped excluye Snacks/Extras/Combos |
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

- [ ] **🚨 Revertir descuento 10% temporal** — 4 productos con `is_featured=1` + `sale_price` (Completo Italiano, Completo Tocino Ahumado, Hass de Filete Pollo, Hass de Carne). Cron programado en VPS: revert automático sáb 25 abr 08:00 Chile (12:00 UTC) + verificación 10:00 Chile (14:00 UTC), ambos con reporte a Telegram. Post-ejecución: limpiar crons y eliminar scripts `apply_sale_today.php` y `revert_sale_today.php`.
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

### 2026-04-25d — Fix comandas: tiempo negativo, notas pago ocultas, minicomandas header legible

**Cambios código:**
- `caja3/src/components/MiniComandas.jsx`: Header de `renderOrderCard` reescrito con separadores (`Retiro | ✓ A TIEMPO | 8:00 | Juan`), tipo pedido con icono, botones ANULAR+Copiar a la derecha, order_number en línea separada.
- `caja3/src/pages/comandas/index.astro`: Fix `getTimeElapsed` y `getMinutesElapsed` — usaban `new Date(createdAt)` + restaban 3h (doble error), ahora usan `.replace(' ','T')+'Z'` como MiniComandas. Filtro en `customer_notes` para ocultar líneas con "EFECTIVO"/"PAGO EN" (info de pago no relevante para cocina).

**Commits:** `5541224`, `8d024b5`
**Deploys:** caja3 ✅ (`8d024b5`)

### 2026-04-25c — Spec recetas-categorias-bebidas: implementación + refactor bebidas a productos reales

**Cambios código:**
- `mi3/backend/app/Services/Recipe/BeverageService.php`: Reescrito — getBeverages() ahora consulta productos de categorías Snacks/Bebidas (53 productos reales), no ingredientes. createBeverageProduct() crea producto con todos los campos (nombre, precio, descripción, costo, stock, subcategoría, SKU). getSubcategories() para Snacks/Bebidas.
- `mi3/backend/app/Http/Controllers/Admin/BeverageController.php`: store() crea producto (no ingrediente), storeProduct() eliminado, subcategories() agregado.
- `mi3/backend/routes/api.php`: POST bebidas/producto → GET bebidas/subcategorias.
- `mi3/backend/app/Services/Recipe/RecipeService.php`: getRecipesGroupedByCategory() excluye Bebidas+Snacks+Personalizar+Extras+Combos.
- `mi3/backend/app/Http/Controllers/RecipeController.php`: index() soporta ?grouped=true.
- `mi3/frontend/app/admin/recetas/bebidas/page.tsx`: Reescrito — muestra productos agrupados por subcategoría (Aguas, Latas 350ml, Energéticas, etc.) con accordion, formulario con campos completos.
- `mi3/frontend/app/admin/recetas/page.tsx`: Refactorizado a accordion por categoría.
- `mi3/frontend/components/admin/sections/RecetasSection.tsx`: Tab Bebidas (Wine icon).

**Commits:** `b2a0623`, `ea72361`, `9b63c08`
**Deploys:** mi3-backend ✅, mi3-frontend ✅ (ambos `9b63c08`)

### 2026-04-25b — Spec turnos-nomina-mejoras: implementación completa + deploy

**Cambios código:**
- `mi3/backend/app/Http/Controllers/Admin/PayrollController.php`: Extendido — expone `total_reemplazando`, `total_reemplazado`, `reemplazos_realizados[]`, `reemplazos_recibidos[]` agregados de ambos centros de costo. Agrega `credito_r11_pendiente` consultando `usuarios.credito_r11_usado` y verificando si ya se descontó via `ajustes_sueldo`. Recalcula `gran_total = base + reemplazando - reemplazado + ajustes`.
- `mi3/frontend/components/admin/sections/NominaSection.tsx`: Tarjetas expandibles con ChevronDown/Up, grid compacto con +Reemp (verde) y -Reemp (rojo), desglose completo al expandir (reemplazos realizados/recibidos con días y montos, ajustes, crédito R11 pendiente con "Se descontará el día 1").
- `mi3/frontend/components/admin/sections/TurnosSection.tsx`: Calendario compacto (72px vs 100px), avatares mini 24px agrupados R11|Seguridad, borde naranja izquierdo en días con reemplazo, punto naranja en móvil, detalle con titular tachado → reemplazante + monto.

**Commits:** `2207cc8`
**Deploys:** mi3-backend ✅, mi3-frontend ✅ (ambos `2207cc8`)

### 2026-04-25a — Fix clientes "Load failed" en admin/personal (500 CORS)

**Causa raíz:** `UserController::customers()` usaba `SUM(tuu_orders.total)` pero la columna `total` no existe en `tuu_orders`. La columna correcta es `product_price`. SQL error → 500 → Laravel no envía headers CORS → navegador reporta error CORS.

**Cambios código:**
- `mi3/backend/app/Http/Controllers/Admin/UserController.php`: `tuu_orders.total` → `tuu_orders.product_price`

**Commits:** `180d707`
**Deploys:** mi3-backend ✅ (`180d707`)

---

> Sesiones anteriores (170+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-24b archivadas. Últimas: 2026-04-24b (Descuento temporal 10%), 2026-04-24a (Redeploy mi3-frontend), 2026-04-23d (Fix P&L completo).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
