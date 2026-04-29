# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-29)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`d880e70`) — delivery config centralizado BD, card_surcharge separado |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`4540368`) — MiniComandas: chevron "Ver pedido 👀" mapa embed, "Enviar a Rider" azul |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`470d95c`) — Dashboard Pro: split layout, collapsible EdR, CMV, charts Recharts, monitor vivo |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`470d95c`) — 3 endpoints nuevos: top-products, cmv, monthly |
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

### 2026-04-29d — Dashboard Pro: split layout, charts Recharts, CMV, monitor vivo

**Cambios código:**
- `mi3/backend/app/Services/Ventas/VentasService.php`: 3 métodos nuevos — `getTopProducts()` (top 10 por qty/profit), `getCmvBreakdown()` (ingredientes por costo), `getMonthlyAggregates()` (últimos 6 meses apilados).
- `mi3/backend/app/Http/Controllers/Admin/VentasController.php`: 3 endpoints nuevos — `GET top-products`, `GET cmv`, `GET monthly`.
- `mi3/backend/routes/api.php`: Rutas nuevas + import VentasController.
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: Reescritura completa — split 50/50 desktop (datos izq, charts der), mobile apilado. LiveSalesMonitor con WS indicator + sonido toggle + últimas ventas expandibles. EdR en CollapsibleSection con chevron.
- `mi3/frontend/components/admin/dashboard/CollapsibleSection.tsx`: Componente reutilizable chevron con accent color, aria-expanded.
- `mi3/frontend/components/admin/dashboard/CmvSection.tsx`: Tabla ingredientes por costo, highlight >10% CMV.
- `mi3/frontend/components/admin/dashboard/MonthlyChart.tsx`: Recharts barras apiladas (ventas/costo/delivery) últimos 6 meses.
- `mi3/frontend/components/admin/dashboard/TopProductsChart.tsx`: Recharts horizontal bar, toggle vendidos/rentables.
- Dependencia: `recharts` instalada.

**Commits:** `470d95c`
**Deploys:** mi3-frontend ✅, mi3-backend ✅. Endpoints verificados en producción via SSH.

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

### 2026-04-29a — Botón cancelar delivery rider + waypoints ruta embed

**Cambios código:**
- `mi3/backend/app/Http/Controllers/Public/PublicRiderController.php`: Status `reject` — limpia `rider_last_lat/lng` a NULL, revierte `order_status` a `ready`.
- `mi3/frontend/components/rider/PublicRiderView.tsx`: Botón rojo ✕ cancelar (visible cuando GPS activo), `rejectDelivery()` con confirm dialog.
- `mi3/frontend/components/rider/RiderMapEmbed.tsx`: Ruta con waypoints Rider→R11→Destino, pin destino geocodificado, 3 markers.

**Commits:** `23c1885`, `9240f7f`
**Deploys:** mi3-frontend ✅, mi3-backend ✅

---

> Sesiones anteriores (185+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-28f archivadas. Últimas archivadas: 2026-04-29a (Botón cancelar rider), 2026-04-28f (MiniComandas chevron embed), 2026-04-28e (Rider fullscreen map-first).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
