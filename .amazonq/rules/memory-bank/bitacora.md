# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-05-01)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`3dafb96`) — leaf-only inventory tracking para compuestos |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`11d843f`) — Fix delivery display MiniComandas + VentasDetalle |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`8522d20`) — Fix NominaSection Error #185 useMemo trailing |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`c47406e`) — Fix aprobar() double deduction + cron month + EdR fallback |
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
- [x] **🚨 Corregir ajustes_sueldo mayo→abril en BD** — COMPLETADO. IDs 82,83,84 (Crédito R11) y 85 (adelanto) actualizados de `mes=2026-05-01` a `mes=2026-04-01`, conceptos corregidos de "mayo" a "abril". Mayo ahora tiene 0 registros.
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

- [x] **🚨 Fix Tocino Laminado unidades→kg en inventory_transactions** — COMPLETADO. 246 txs corregidas (`quantity * 0.05`). Costos reducidos de $4.715.760 a $273.560. Commit `4c82ec6`.
- [ ] **Spec: Refactor clasificación gastos EdR** — El EdR necesita un spec dedicado. Problemas: 1) Limpieza/Gas se compran como ingredientes pero son OPEX, no CMV. 2) `tipo_compra` en tabla `compras` no refleja la categoría real (todo es 'ingredientes'). 3) Packaging es CMV (se vende con el producto) pero no está separado. 4) Flujo compras IA necesita clasificar correctamente. Incluir: cómo se registran compras, qué es CMV vs OPEX, mermas, consumos directos.
- [ ] **Utilidad dueño mal calculada en Nómina** — Yojhans muestra -$322k como "a pagar" con 0 días. Esto es la utilidad (resultado neto), no nómina. Revisar cómo se presenta en la UI — no debería parecer una liquidación de sueldo.
- [ ] **Nómina abril $593.333 proyectada** — Es promedio de últimos 3 meses (pagos_nomina). Falta registrar nómina real de abril cuando se pague. ~~Además: trazabilidad nómina↔créditos↔ajustes no está conectada en el dashboard~~ RESUELTO: NominaSection reescrita con tabs Ruta11/Seguridad, detalle ajustes+créditos inline por trabajador, resumen de pagos modal. Falta: registrar pago real de abril.
- [ ] **Discrepancia ventas mensuales vs EdR** — Gráfico mensual usa CONVERT_TZ pero puede haber diferencia residual con caja3 API. Verificar que ambos coincidan para abril.

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

### 2026-05-01b — Fix 4 bugs nómina/EdR: cron month, EdR fallback, React #185

**Cambios código:**
- `mi3/backend/app/Services/Credit/R11CreditService.php`: `autoDeduct()` — `now()->format('Y-m')` → `now()->subMonth()->format('Y-m')`. Descuento crédito R11 ahora se atribuye al mes anterior (cuando se consumió el crédito).
- `mi3/backend/app/Services/Loan/LoanService.php`: `procesarDescuentosMensuales()` — mismo fix `subMonth()`. Descuento adelanto de sueldo ahora se atribuye al mes anterior. ADEMÁS: `aprobar()` ahora marca `estado='pagado'` y `cuotas_pagadas=cuotas` inmediatamente para prevenir doble descuento (cron encontraba préstamo como "pendiente" y descontaba de nuevo).
- `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`: Eliminado guard `$isCurrentMonth` en fallback NominaService. EdR ahora muestra nómina calculada para cualquier mes, no solo el actual.
- `mi3/frontend/components/admin/sections/NominaSection.tsx`: `trailing` JSX extraído a `useMemo([data, activeTab, generatingLink])`. Previene loop infinito de re-renders (Error #185) por referencia inestable en `onHeaderConfig`.
- `mi3/backend/routes/console.php`: Crons `mi3:r11-auto-deduct` y `mi3:loan-auto-deduct` cambiados de 06:00/06:30 a 02:00/02:30 hora Chile.

**Spec:** `.kiro/specs/fix-nomina-edr-bugs/` — bugfix.md + design.md + tasks.md completos.

**Pendiente:** ~~Corregir en BD los ajustes_sueldo creados hoy (1 mayo) con `mes = 2026-05-01` → `mes = 2026-04-01`~~ COMPLETADO. IDs 82-85 corregidos via tinker.

**Commits:** `8522d20`, `df77b18`, `c47406e`
**Deploys:** mi3-backend ✅ (×3), mi3-frontend ✅.
**BD:** IDs 82-84 ajustes_sueldo corregidos `mes=2026-05-01` → `mes=2026-04-01`, conceptos "mayo"→"abril". ID 85 eliminado (doble descuento adelanto $50.000 a Andrés Aguilera). Andrés: $292.154→$342.154.

### 2026-05-01a — Fix MiniComandas + VentasDetalle delivery display doble descuento visual

**Cambios código:**
- `caja3/src/components/MiniComandas.jsx`: Fix display delivery en comandas. `delivery_fee` en BD ya es el monto descontado, pero se mostraba como base y luego se restaba `delivery_discount` de nuevo visualmente. Ahora: base = `delivery_fee + delivery_discount` (con line-through), descuento RL6 con % dinámico, Total Delivery = `delivery_fee` (monto real cobrado).
- `caja3/src/components/VentasDetalle.jsx`: Mismo fix en Detalle de Ventas. Muestra base reconstruido + descuento RL6 + total correcto.

**Commits:** `46d0d85`, `11d843f`
**Deploys:** caja3 ✅ (×2).

### 2026-04-30f — Merma Smart: conversión auto + UX lista unificada

**Cambios código:**
- `caja3/src/components/MermaPanel.jsx`: Reescritura completa — (1) Lista unificada ingredientes+productos en un solo buscador (productos con badge "prod"). (2) Items agregados en 1 fila compacta: nombre + stepper[-1+] o input decimal + unidad texto + costo + X. (3) Sin `<select>` nativo de unidades. (4) Botón "Siguiente" como footer fijo abajo con total visible. (5) Ingredientes con `peso_por_unidad` muestran stepper entero con conversión auto a kg. Stock muestra equivalencia natural ("≈ 21 tomates").
- `caja3/src/utils/mermaUtils.js`: 8 funciones smart — `getMermaInputType()`, `convertToBaseUnit()`, `calculateSmartCost()`, `getConversionText()`, `stockInNaturalUnits()`, `validateSmartQuantity()`, `getSmartPlaceholder()`, `getSmartQuestion()`.
- `caja3/api/registrar_merma.php`: Acepta `cantidad_natural`, auto-convierte via `peso_por_unidad`. `GREATEST(stock - qty, 0)` previene stock negativo.
- `caja3/sql/merma_smart_columns.sql`: Migración + seed data.

**BD:** `ALTER TABLE ingredients ADD COLUMN peso_por_unidad DECIMAL(10,4), ADD COLUMN nombre_unidad_natural VARCHAR(50)`. Seeded: Tomate 0.150, Cebolla 0.200, Cebolla morada 0.200, Palta 0.200, Papa 0.200, Mango 0.300, Maracuyá 0.150.

**Commits:** `a41e39d`, `900cc23`, `f541622`, `863c3b3`
**Deploys:** caja3 ✅ (×4).

### 2026-04-30e — Quitar checklists de app trabajadores (mi3 worker)

**Cambios código:**
- `mi3/frontend/lib/navigation.ts`: Eliminado item `checklist` de `secondaryNavItems` (worker). Ya no aparece en navegación.
- `mi3/frontend/components/layouts/WorkerSidebar.tsx`: Eliminado import y uso de `usePendingChecklistBadge`. Sidebar desktop sin badge checklist.
- `mi3/frontend/components/mobile/MobileBottomNav.tsx`: Eliminado import y uso de `usePendingChecklistBadge`. Nav mobile sin badge checklist.
- `mi3/frontend/app/dashboard/checklist/page.tsx`: Reemplazado con redirect a `/dashboard` (URLs cacheadas no dan error).

**Decisión:** Checklists solo se usan en comandas (planchero) y caja (cajeras). Worker app no los necesita. Backend API y cron jobs intactos.

**Commits:** `c7e857f`
**Deploys:** mi3-frontend ✅.

### 2026-04-30d — Fix error boundaries admin SPA + null-safety DashboardSection

**Cambios código:**
- `mi3/frontend/app/admin/error.tsx`: NUEVO. Error boundary a nivel de ruta Next.js — fallback con botón "Reintentar" e "Ir al inicio" en vez del error críptico genérico.
- `mi3/frontend/components/admin/AdminShell.tsx`: `SectionErrorBoundary` class component — cada sección lazy-loaded envuelta en su propio error boundary. Eliminado `refreshCounters` key-based re-mount que causaba loop infinito (re-mount → re-subscribe WebSocket → evento → re-mount).
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: `Promise.all` → `Promise.allSettled`. WebSocket listener estabilizado con refs (subscribe once, never re-subscribe). Null-coalescing en payload. `console.error` en catches.
- `mi3/frontend/components/mobile/MobileBottomNav.tsx`: Fix import faltante `usePendingChecklistBadge`.

**Diagnóstico:** React error #185 (Maximum update depth exceeded) causado por: 1) AdminShell `refreshCounters` cambiaba key del componente → re-mount → re-subscribe WebSocket → evento → setState → re-mount = loop infinito. 2) DashboardSection WebSocket useEffect tenía `[fetchData, fetchShift, isCurrentMonth]` como deps → cada cambio de mes re-suscribía al canal.

**Commits:** `ce290c5`, `c7e857f`
**Deploys:** mi3-frontend ✅ (×2).

### 2026-04-30c — Nómina: página pública /nomina/TOKEN, créditos R11 solo Ruta11, encoding BD

**Cambios código:**
- `mi3/backend/app/Http/Controllers/Admin/PayrollController.php`: Crédito R11 solo en `$centro === 'ruta11'`. Nuevos métodos `generateSnapshot()` (POST snapshot JSON a BD) y `showSnapshot()` (GET público sin auth).
- `mi3/backend/app/Models/NominaSnapshot.php`: Modelo con token auto-generado `Str::random(12)`, cast `data` a array.
- `mi3/backend/database/migrations/2026_04_30_000001_create_nomina_snapshots_table.php`: Tabla `nomina_snapshots` (token unique, mes, data JSON).
- `mi3/backend/database/migrations/2026_04_28_*`: Guards `Schema::hasTable`/`hasColumn` para delivery_config y card_surcharge (ya existían en prod).
- `mi3/backend/routes/api.php`: Ruta pública `GET /nomina/{token}`, ruta admin `POST /payroll/snapshot`.
- `mi3/frontend/app/nomina/[token]/page.tsx`: Página pública estilo rendición — 2 secciones separadas (Ruta11/Seguridad) con totales independientes, chevrones expandibles, iconos lucide (Wallet/TrendingDown/CreditCard/ArrowUpRight/ArrowDownRight), detalle ajustes/créditos/reemplazos, share button genera mensaje corto (solo subtotales + link). Texto reemplazos descriptivo: `→ Reemplazó a Claudio · 12 abr` en vez de `→ Ricardo · días 14`.
- `mi3/frontend/components/admin/sections/NominaSection.tsx`: Botón "Resumen" genera snapshot y abre `/nomina/TOKEN` en nueva pestaña. Eliminado `ResumenPagosModal` (250 líneas dead code), limpieza imports.

**BD:** Fix encoding `ajustes_categorias` id=8: `PrÃ©stamo` → `Préstamo`. Migración `nomina_snapshots` ejecutada. Guards migraciones delivery_config + card_surcharge. Fix turno reemplazo Ricardo→Claudio: fecha 14→12 abr (swap tipos entre turnos id=1380 y id=1310).

**Commits:** `a6a2255`, `85ac51e`, `8b4697d`, `f555b17`, `28563f3`, `6cc614e`, `ace82cb`, `25ca83b`, `5a485fb`
**Deploys:** mi3-backend ✅ (×3), mi3-frontend ✅ (×4).

### 2026-04-30b — Nómina: tabs Ruta11/Seguridad, detalle ajustes/créditos, resumen pagos

**Cambios código:**
- `mi3/backend/app/Http/Controllers/Admin/PayrollController.php`: Reescrito `index()` — respuesta separada por centro de costo (`ruta11`, `seguridad`), cada uno con `workers`, `summary`, `pagos`. Workers incluyen `descuentos` y `bonos` detallados (ajustes negativos/positivos separados con id para eliminar), `credito_r11_pendiente`, `total_a_pagar` (sueldo_base + reemplazos + ajustes - créditos). Summary: `total_sueldos_base`, `total_descuentos`, `total_creditos`, `total_a_pagar`.
- `mi3/frontend/components/admin/sections/NominaSection.tsx`: Reescritura completa — 2 tabs (La Ruta 11 / Cam Seguridad) via `onHeaderConfig`, resumen por centro con Presupuesto→Sueldos→Descuentos→Créditos→Total a Pagar, cards expandibles por trabajador con detalle de reemplazos/bonos/descuentos (cada uno con botón eliminar), crédito R11 pendiente, breakdown total. Modal "Resumen de Pagos" con lista por centro + TOTAL NÓMINA combinado. Trailing en header muestra total del tab activo.
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: EdR skeleton loading — `edrLoading` state separado para no re-renderizar panel completo al navegar meses.

**Commits:** `62acbee`
**Deploys:** mi3-frontend ✅, mi3-backend ✅.

### 2026-04-30a — Fix CMV doble conteo compuestos + leaf-only tracking app3

**Cambios código:**
- `mi3/backend/app/Services/Ventas/VentasService.php`: `getCmvBreakdown()` — `totalCmv` ahora excluye `exclusiveChildIds` (antes solo el breakdown los excluía, inflando el % CMV). Guard `tableExists('ingredient_recipes')`.
- `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`: `costo_ingredientes` del EdR ahora usa `VentasService::getCmvBreakdown('month')` como fuente única en vez de caja3 `get_sales_analytics.php` (que usaba `item_cost * quantity`, fuente diferente e inflada). Meses históricos también usan VentasService (antes usaban `item_cost * quantity` de `tuu_order_items`).
- `mi3/backend/app/Http/Controllers/Admin/VentasController.php`: Endpoint `/ventas/cmv` acepta `?month=YYYY-MM` para consultar meses específicos.
- `mi3/backend/app/Services/Ventas/VentasService.php`: `getCmvBreakdown()` acepta parámetro opcional `$month` para override del date range.
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: CMV fetch pasa `&month=YYYY-MM` al navegar meses históricos.
- `app3/api/process_sale_inventory_fn.php`: Agregado `resolveIngredientDeductionApp()` — explota ingredientes compuestos a hijos (leaf-only). Query `product_recipes` ahora incluye `i.is_composite`.

**BD:** Eliminadas 132 transacciones duplicadas de Hamburguesa R11 (id=48) en órdenes donde ya existían txs de hijos (Carne Molida, Tocino, Longaniza). Costo inflado eliminado: $261.468. Overlap: 0.

**Diagnóstico:** app3 no tenía `resolveIngredientDeduction` (caja3 sí). Desde 2025-11-11 cuando Hamburguesa R11 se marcó `is_composite=1`, app3 seguía creando txs del padre + caja3 creaba txs de hijos = doble conteo en 94 órdenes (132 txs). Además, el EdR usaba caja3 como fuente de CMV (item_cost) mientras el breakdown usaba inventory_transactions — fuentes inconsistentes. Y el breakdown no respetaba la navegación de meses (siempre mostraba abril).

**Commits:** `4ada487`, `3dafb96`, `2ef6474`, `8ea3fa1`, `3df0f63`, `e4d6c04`, `1974508`, `df68714`
**Deploys:** mi3-backend ✅ (×6), mi3-frontend ✅ (×3), app3 ✅.

---

> Sesiones anteriores (190+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-30e archivadas. Últimas archivadas: 2026-04-30e (Quitar checklists worker), 2026-04-30d (Fix error boundaries), 2026-04-30c (Nómina página pública).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
