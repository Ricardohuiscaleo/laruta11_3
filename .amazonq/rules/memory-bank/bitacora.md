# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-30)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`3dafb96`) — leaf-only inventory tracking para compuestos |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`4540368`) — MiniComandas: chevron "Ver pedido 👀" mapa embed, "Enviar a Rider" azul |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`ce290c5`) — Error boundaries admin SPA, null-safety DashboardSection |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`28563f3`) — Nómina: snapshot API, tabla nomina_snapshots, guards migraciones |
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

### 2026-04-30d — Fix error boundaries admin SPA + null-safety DashboardSection

**Cambios código:**
- `mi3/frontend/app/admin/error.tsx`: NUEVO. Error boundary a nivel de ruta Next.js — fallback con botón "Reintentar" e "Ir al inicio" en vez del error críptico genérico.
- `mi3/frontend/components/admin/AdminShell.tsx`: `SectionErrorBoundary` class component — cada sección lazy-loaded envuelta en su propio error boundary. Si una sección crashea, solo esa muestra error con botón reintentar, las demás siguen funcionando.
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: `Promise.all` → `Promise.allSettled` para que un API fallido no mate al otro. Null-coalescing en WebSocket payload (`payload?.order`). `console.error` en catches para diagnóstico (antes eran `catch {}` silenciosos).

**Diagnóstico:** "Application error: a client-side exception has occurred" aparecía porque no había ningún Error Boundary en la app admin. Cualquier error en cualquier componente (dato null del API, red, WebSocket) crasheaba toda la página sin recuperación.

**Commits:** `ce290c5`
**Deploys:** mi3-frontend ✅.

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

### 2026-04-29e — Fix datos: Tocino, Montina Big, CMV trazabilidad, nómina real

**Cambios código:**
- `mi3/backend/app/Services/Ventas/VentasService.php`: `getMonthlyAggregates` — CONVERT_TZ Chile, NominaService mes actual, `nomina_projected` flag. `getCmvBreakdown` — limit 50, `untracked_cmv` field para gap trazabilidad.
- `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`: Param `?month=YYYY-MM`, `$isCurrentMonth` para fuente datos.
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: Nav meses ◀▶, header neutral-800, resultado neto sólido rojo/verde, fila "Sin trazabilidad" en CMV.

**BD:** Tocino Laminado: 246 txs corregidas `quantity*0.05` (unidades→kg), costos $4.7M→$274k. Montina Big: cost_per_unit $479→$269. Nómina histórica: 4 registros pagos_nomina Oct-Ene.

**Commits:** `c21b21e`→`863b921` (10+ commits)
**Deploys:** mi3-frontend ✅, mi3-backend ✅.

### 2026-04-29d — Dashboard Pro: split layout, charts, monitor turno, UX fixes iterativos

**Cambios código:**
- `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`: Param `?month=YYYY-MM` para navegar meses históricos. Meses pasados usan tuu_orders directo (no caja3). `$isCurrentMonth` controla qué fuente usar.
- `mi3/backend/app/Services/Ventas/VentasService.php`: 3 métodos nuevos + `getMonthlyAggregates` con `CONVERT_TZ` UTC→Chile, nómina de `pagos_nomina.mes`, proyección avg 3 meses si no hay datos, `nomina_projected` flag.
- `mi3/backend/app/Http/Controllers/Admin/VentasController.php`: 3 endpoints — `GET top-products`, `GET cmv`, `GET monthly`.
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: Reescritura iterativa — split 50/50, monitor turno (shift_today + WS), EdR single card con chevrones inline, header `bg-neutral-800` con nav meses ◀▶, Resultado Neto sólido (`bg-red-600`/`bg-green-600` + white text `text-xl font-black`).
- `mi3/frontend/components/admin/dashboard/MonthlyChart.tsx`: 1 columna apilada (ventas+costo+nómina+delivery), header resultado, tooltip "(proy.)" para nómina proyectada.
- `mi3/frontend/components/admin/dashboard/TopProductsChart.tsx`: Pareto dual-axis + diagnóstico auto expandible (insights Pareto/margen/oportunidad).
- Dependencia: `recharts` instalada.

**BD:** Insertados 4 registros `pagos_nomina`: Oct-Dic 2025 ($1.590.000 c/u), Ene 2026 ($1.500.000).

**Commits:** `470d95c`→`03636c0` (10+ commits iterativos)
**Deploys:** mi3-frontend ✅, mi3-backend ✅ (múltiples iteraciones).

### 2026-04-29c — Ventas: excluir RL6 de métricas + detalle compacto con stock

**Cambios código:**
- `mi3/backend/app/Services/Ventas/VentasService.php`: `WHERE order_number NOT LIKE 'RL6-%'` en `getKpis()`, `getTransactions()`, `getPaymentBreakdown()`. Pagos de crédito RL6/RL6-MANUAL ya no inflan ventas. Helper `excludeCreditPayments()`. Fix stock data: agrupar ingredientes por `order_item_id` (antes usaba `product_id` que era NULL), `abs()` en quantity_used (BD guarda negativos), null-safe en previous_stock/new_stock.
- `mi3/frontend/components/admin/VentasPageContent.tsx`: `OrderDetailPanel` rediseñado — header compacto 1 línea (orden·fecha·pago·cliente), items con precio/costo/utilidad inline, ingredientes en tabla con columnas Antes|Consumo|Después (tabular-nums), totales compactos.

**Commits:** `0e533cd`, `7d5505c`, `4668094`
**Deploys:** mi3-frontend ✅, mi3-backend ✅ (1 retry por error infra Coolify)

### 2026-04-29b — Monitor delivery: pin destino + ruta realtime + datos extra

**Cambios código:**
- `mi3/backend/app/Services/Delivery/DeliveryService.php`: Fix bug `return` antes de `->transform()` (rider_url nunca se agregaba). Campos nuevos en `getActiveOrders()`: `customer_name`, `customer_phone`, `product_price`, `subtotal`, `payment_method`, `delivery_distance_km`, `delivery_duration_min`.
- `mi3/frontend/components/admin/delivery/DeliveryMap.tsx`: Reescritura — pin destino 📍 geocodificado para TODOS los pedidos (azul pulsante en ruta, rojo otros), ruta Directions API rider→destino en tiempo real (throttled), InfoWindow con distancia/duración/total/pago/rider, cache geocode para no repetir llamadas.
- `mi3/frontend/components/admin/delivery/OrderPanel.tsx`: Distancia km + duración min, total CLP + método pago, botón "Llamar cliente" con tel: link.
- `mi3/frontend/components/admin/sections/DeliverySection.tsx`: Bottom sheet "En ruta" mobile con distancia, duración, total, botón llamar.
- `mi3/frontend/hooks/useDeliveryTracking.ts`: Interface `DeliveryOrder` extendida con campos nuevos del backend.

**Commits:** `19852fe`
**Deploys:** mi3-frontend ✅, mi3-backend ✅

---

> Sesiones anteriores (190+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-29b archivadas. Últimas archivadas: 2026-04-29b (Monitor delivery pin destino + ruta realtime), 2026-04-29a (Botón cancelar rider + waypoints), 2026-04-28f (MiniComandas chevron embed).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
