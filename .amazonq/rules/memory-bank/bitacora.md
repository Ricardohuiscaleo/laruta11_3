# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-05-02)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`3dafb96`) — leaf-only inventory tracking para compuestos |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`e3c1bee`) — Fix dispatch photos parsing + AI feedback en VentasDetalle |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`483818b`) — dispatch photos + AI feedback en Ventas detail |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`483818b`) — dispatch_photo_url + image_url en getOrderDetail |
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
- [x] **Investigar por qué `mi3:generate-shifts` del 25 abril no generó turnos de mayo** — RESUELTO. Bug: usaba `now()->format('Y-m')` (mes actual) en vez de `now()->addMonth()` (mes siguiente). El 25 abril generaba turnos de abril (ya existían) → 0 creados. Fix: `addMonth()`. Turnos mayo generados manualmente (93). Commit `748f040`.

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

### 2026-05-02h — Spec packaging-consumo-comandas: steppers bolsas en MiniComandas

**Cambios código:**
- `caja3/api/register_packaging_consumption.php`: NUEVO. Endpoint PHP para registrar consumo de bolsas. Transacciones tipo `consumption`, idempotencia por order_number, stock warnings, PDO transaccional.
- `caja3/src/components/MiniComandas.jsx`: Estado `packagingQty` + helpers. Delivery: grid 4 columnas (2 fotos + 2 bolsas) con header "FOTOS DELIVERY / BOLSAS". Pickup: grid 2 columnas solo bolsas con header "BOLSAS DELIVERY". Cuadrados h-28 con foto real, stepper (−/+), highlight amber cuando qty>0. Pre-fill 1 bolsa grande para delivery. Ocultos en delivery fase 2. `deliverOrder` y `dispatchToDelivery` llaman a `registerPackaging`.

**Spec:** `.kiro/specs/packaging-consumo-comandas/` — requirements + design + tasks completos.

**Commits:** `432850e`, `1e6b5c7`, `c8d79d9`
**Deploys:** caja3 ✅ (×3).

### 2026-05-02g — Fix rutas /rider/* públicas en middleware (delivery tracking + embed)

**Diagnóstico:** Links de delivery enviados por WhatsApp desde MiniComandas (`https://mi.laruta11.cl/rider/{orderId}`) y el iframe embed (`/rider/{orderId}/embed`) redirigían a `/login` porque el middleware de Next.js no los tenía en la lista de rutas públicas. Las páginas en sí (`PublicRiderView`, `RiderMapEmbed`) son componentes públicos sin `useAuth()`.

**Cambios código:**
- `mi3/frontend/middleware.ts`: Agregado `pathname.startsWith('/rider')` a la lista de rutas públicas. Cubre `/rider`, `/rider/{id}` y `/rider/{id}/embed`.

**Commits:** `a388f67`
**Deploys:** mi3-frontend ✅.

### 2026-05-02f — Fix subida imágenes productos mi3 + botón ⚡IA descripción Gemini

**Diagnóstico:** Imágenes subidas desde `mi.laruta11.cl/admin/recetas` daban 403 al intentar verlas. `RecipeController::uploadProductImage()` usaba Flysystem (`Storage::disk('s3')->put()`) que no sube correctamente al bucket (bug conocido, documentado en `ImagenService`). Las imágenes de caja3 funcionaban porque usan POST policy directo.

**Cambios código:**
- `mi3/backend/app/Http/Controllers/RecipeController.php`: `uploadProductImage()` reescrito — reemplazado Flysystem por PUT directo con SigV4 (mismo patrón que `ImagenService.putObject()`). Sin `x-amz-acl` (bucket tiene Block Public ACLs, bucket policy maneja lectura pública). Nuevo método `generateDescription()` para generar descripciones con IA. Nuevo método `suggestedPackaging()` para auto-agregar insumos de packaging por categoría.
- `mi3/backend/app/Services/Recipe/RecipeAIService.php`: Nuevo método `generateDescription()` — usa Gemini Flash Lite para generar descripción corta (max 120 chars) basada en nombre+ingredientes+categoría. Guarda en BD, loguea tokens y costo CLP.
- `mi3/backend/routes/api.php`: Nuevas rutas `POST recetas/{productId}/generate-description` y `GET recetas/{categoryId}/suggested-packaging`.
- `mi3/frontend/app/admin/recetas/page.tsx`: Botón ⚡IA junto al label "Descripción" en RecipeEditor. Botón ⚡Packaging en sección Insumos (solo para Sandwiches/Hamburguesas/Papas) que auto-agrega packaging desde `portion_standards`.

**Commits:** `b515812`, `695a97a`, `ba5b0b3`, `8aaa196`
**Deploys:** mi3-backend ✅ (×4), mi3-frontend ✅ (×3).

### 2026-05-02e — Fix Gemini vision + upgrade modelo + pipeline UI metadata + remove delays

**Diagnóstico:** Pipeline multi-agente fallaba en fase Visión. API key y modelo funcionan. Compresión ya existía (frontend 1200px + backend 1200x800 + S3).

**Cambios código:**
- `GeminiService.php`: Modelo `gemini-2.5-flash-lite` → `gemini-3.1-flash-lite-preview`. Logging detallado. Retry 20s→30s. `parseResponse()` loguea blockReason.
- `PipelineExtraccionService.php`: Resize >1536px. Emite `imageSizeKb` en SSE. Error incluye modelo+elapsed.
- `ImagenService.php`: `uploadTemp()` retorna metadata (tamaño, resolución, % reducción).
- `CompraController.php`: Pasa metadata al frontend.
- `MobileExtractionSheet.tsx`: Muestra metadata upload y KB→Gemini. Eliminado auto-close 1.5s, botón "Listo" real.
- `registro/page.tsx`: Captura uploadMeta. Eliminado setTimeout 3s auto-close.

**Commits:** `5e61952`, `1144cbf`, `1af11b9`, `f79f7c6`, `1dd9951`
**Deploys:** mi3-backend ✅ (×3), mi3-frontend ✅.

### 2026-05-02d — Limpieza packaging de recetas: bolsas, pocillos, papel mantequilla

**BD:** Eliminados ingredientes de packaging de `product_recipes` que no son 1:1 con el producto:
- Bolsa Delivery Baja (id=43): eliminada de 28 recetas. Stock reseteado de -590 → 0.
- Pocillo Salsero (id=103): eliminado de 4 recetas.
- Papel Mantequilla (id=42): eliminado solo de Papas Fritas (product_id=17). Se mantiene en 10 productos (5 sandwiches + 5 hamburguesas).
- Cajas (sandwich, completo, papa, aluminio, pizza) se mantienen en recetas (son 1:1).

**Decisión:** Bolsas se gestionarán desde MiniComandas (caja3) al despachar, no por receta. Salsas/pocillos serán seleccionables desde caja3. Bolsa Delivery Baja será reemplazada por 2 modelos (mediana y grande).

**Commits:** ninguno (cambio solo en BD).
**Deploys:** ninguno.

### 2026-05-02c — Combo editor: desglose ingredientes por item fijo + tocino 40g BD

**Cambios código:**
- `mi3/backend/app/Services/Recipe/ComboService.php`: `getComboDetail()` ahora pre-carga recetas (ingredientes) de cada item fijo via `product_recipes` + `ingredients`. Cada fixed_item incluye array `ingredients` con nombre, cantidad, unidad y costo calculado. Nuevo método `calculateIngredientCostForCombo()` con conversión kg↔g, lt↔ml.
- `mi3/frontend/app/admin/recetas/combos/page.tsx`: Nueva interfaz `RecipeIngredient`. Items fijos muestran desglose de ingredientes debajo (nombre, cantidad+unidad, costo). Cost Summary también muestra ingredientes por item fijo multiplicados por cantidad.

**BD:** 7 recetas de Tocino Laminado ajustadas a múltiplos de 40g (peso real lámina): Completo Tocino 50→40g, Pichanga 50→40g, Tocino Extra 50→40g, Pizza Mediana 50→40g, Cheeseburger 100→80g, Pizza Familiar 100→80g, Triple XXXL 150→120g.

**Commits:** `9d5a744`
**Deploys:** mi3-backend ✅, mi3-frontend ✅.

### 2026-05-02i — Dispatch photos + AI feedback en Ventas detail (mi3 + caja3)

**Cambios código:**
- `mi3/backend/app/Services/Ventas/VentasService.php`: `getOrderDetail()` trae `dispatch_photo_url` y `image_url` (LEFT JOIN products). Parsea JSON de fotos a array `dispatch_photos` con type, url, verification.
- `mi3/frontend/components/admin/VentasPageContent.tsx`: Nueva interfaz `DispatchPhoto`. Sección "📷 Fotos de Entrega" con thumbnails 80×80, borde verde/ámbar según verificación IA, feedback texto por foto. Product images 32×32 en cada item.
- `caja3/src/components/VentasDetalle.jsx`: Fix bug — parseo de `dispatch_photo_url` ahora soporta formato objeto `{productos: {url, verification}, bolsa: {url, verification}}` además del array legacy. Thumbnails con borde verde/ámbar, label tipo foto, panel feedback IA. Visor fullscreen usa `photo.url`.

**Commits:** `483818b`, `701216e`, `e3c1bee`
**Deploys:** mi3-backend ✅, mi3-frontend ✅, caja3 ✅ (2 retries por extra closing div).

### 2026-05-02h — Product images en Ventas detail modal + git-sync-check hook

**Cambios código:**
- `mi3/backend/app/Services/Ventas/VentasService.php`: `getOrderDetail()` Query 2 ahora hace `LEFT JOIN products` para traer `image_url`. Campo incluido en respuesta JSON de cada item.
- `mi3/frontend/components/admin/VentasPageContent.tsx`: Interfaz `OrderDetailItem` incluye `image_url`. `OrderDetailPanel` muestra miniatura 32×32px al lado del nombre del producto (condicional, solo si tiene imagen).
- `.kiro/hooks/git-sync-check.kiro.hook`: Nuevo hook `promptSubmit` que al enviar mensaje hace `git fetch` + compara commits + `git pull` automático si está atrás.

**Commits:** `8cab878`
**Deploys:** mi3-backend ✅, mi3-frontend ✅.

---

> Sesiones anteriores (190+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-05-02c archivadas. Últimas archivadas: 2026-05-02c (Combo editor ingredientes), 2026-05-02b (Tocino 40g BD), 2026-05-02a (Fix combo editor cost_price).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
