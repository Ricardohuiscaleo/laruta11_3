# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-23)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running (`fe30703`) — UX: scrollLockRef, bebidas subcategorías sync checkout+personalización |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running (`440fcdf`) — menú lista compacta, búsqueda inline highlight, arqueo tabla 3-col |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`9fb40c3`) — Estado de Resultados P&L dashboard (fix contable), Tabs Créditos/Usuarios |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`9fb40c3`) — Dashboard P&L endpoint (CMV sin duplicar compras), RL6CreditService, GmailService |
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

### 2026-04-23a — Spec admin-credits-users-tabs: implementación completa + deploy

**Cambios:**
- `mi3/backend/app/Services/Credit/RL6CreditService.php`: Creado — getRL6Users() con cálculo moroso/días_mora/deuda_ciclo_vencido, getSummary(), approveCredit(), rejectCredit(), manualPayment() con desbloqueo automático, calculateEmailEstado(), previewEmail(), buildEmailHtml() replicando caja3 email template.
- `mi3/backend/app/Http/Controllers/Admin/CreditController.php`: Extendido — 7 métodos RL6: rl6Index, rl6Approve, rl6Reject, rl6ManualPayment, rl6PreviewEmail, rl6SendEmail, rl6SendBulkEmails.
- `mi3/backend/app/Http/Controllers/Admin/UserController.php`: Creado — customers() con left join tuu_orders, búsqueda LIKE, order by id DESC.
- `mi3/backend/app/Services/Email/GmailService.php`: Extendido — sendRL6CollectionEmail() con email_logs.
- `mi3/backend/app/Models/Usuario.php`: Campos RL6 en $fillable + relaciones rl6Transactions/emailLogs.
- `mi3/backend/app/Models/Rl6CreditTransaction.php`: Creado.
- `mi3/backend/routes/api.php`: 8 rutas nuevas (7 RL6 + 1 customers).
- `mi3/frontend/components/admin/AdminSidebarSPA.tsx`: 'Créditos R11'→'Créditos', 'Personal'→'Usuarios'.
- `mi3/frontend/components/admin/MobileBottomNavSPA.tsx`: Mismos cambios labels.
- `mi3/frontend/components/admin/AdminShell.tsx`: SECTION_TITLES actualizados.
- `mi3/frontend/components/admin/sections/CreditosSection.tsx`: Reescrito — tabs R11/RL6, tabla RL6 con badges moroso (rojo/naranja/verde), acciones approve/reject/pago manual, botón email preview/envío, bulk "Cobrar a Morosos", CreditSummaryTrailing integrado.
- `mi3/frontend/components/admin/EmailPreviewModal.tsx`: Creado — iframe preview, badge tipo email, botones cancelar/enviar.
- `mi3/frontend/components/admin/CreditSummaryTrailing.tsx`: Creado — 5 métricas RL6 / 4 métricas R11, colores condicionales, responsive mobile 3 métricas, skeleton loading.
- `mi3/frontend/components/admin/sections/PersonalSection.tsx`: Reescrito — tabs Work/Clientes, tabla clientes app3 con búsqueda debounce 300ms.
- `mi3/frontend/types/admin.ts`: Creado — RL6CreditUser, RL6Summary, CustomerUser, EmailEstado.
- Fix: instalado @vis.gl/react-google-maps (dependencia faltante preexistente).

**Commits:** `63c3552`
**Deploys:** mi3-backend ✅, mi3-frontend ✅ (ambos `63c3552`)

### 2026-04-22e — Estado de Resultados P&L en dashboard admin + fix duplicación contable

**Cambios:**
- `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`: Reescrito — endpoint `/admin/dashboard` retorna P&L: ingresos (ventas_netas, total_ordenes, ticket_promedio), costo_ventas (costo_ingredientes/CMV, margen_bruto, margen_bruto_pct), gastos_operacion (nomina_ruta11 como único OPEX), resultado (resultado_neto, resultado_neto_pct), meta (meta_mensual, porcentaje_meta, ventas_proyectadas), flujo_caja (compras_mes separado del P&L). Fix contable: compras movidas de OPEX a flujo_caja para evitar duplicación con CMV.
- `mi3/frontend/components/admin/sections/DashboardSection.tsx`: Reescrito — Estado de Resultados: header dark, barra meta mensual, 3 KPI pills (Pedidos, Ticket, Proyección), tabla P&L coloreada (Ingresos→CMV→Margen Bruto→Nómina OPEX→Resultado Neto). Sin línea "Compras e Insumos" en OPEX (fix duplicación).
- `.kiro/specs/admin-credits-users-tabs/`: Spec con 10 requirements.

**Commits:** `8f2abe6`, `9fb40c3`
**Deploys:** mi3-backend ✅, mi3-frontend ✅ (ambos `9fb40c3`)

### 2026-04-22d — Bebidas sync + Arqueo tabla + caja3 MenuItem lista compacta

**Cambios:**
- `app3/src/components/MenuApp.jsx`: scrollLockRef click-to-scroll. comboItems.bebidas merge subcategorías 11+61-65.
- `app3/src/components/CheckoutApp.jsx`: Filtro upselling bebidas con IDs 61-65.
- `caja3/api/get_menu_products.php`: subcategoryMap + IDs 61-65.
- `caja3/src/components/MenuApp.jsx`: Rediseño MenuItem de cards grid a lista compacta (imagen 44px + nombre + precio amarillo + ON/OFF + botón agregar). Búsqueda inline filtra listado con highlight amarillo (sin modal sugerencias). Modal fullscreen al tocar foto (descripción + botones). Padding reducido (70px top, pb-24). comboItems.bebidas merge.
- `caja3/api/get_sales_summary.php`: Agregado `r11_credit` al array de métodos de pago.
- `caja3/src/components/ArqueoApp.jsx`: Tabla 3-col (Método|Pedidos|Total), 8 métodos siempre visibles, sin WhatsApp btn, filas sin color especial.
- BD: Dr Pepper (id=210) movida de subcategory_id=11 a 62 (Latas 350ml).

**Commits:** `b1862c7`→`440fcdf` (10 commits)
**Deploys:** app3 ✅ (`fe30703`), caja3 ✅ (`440fcdf`)

### 2026-04-22c — UX app3: scroll continuo, rojo sólido, eliminar hamburguesas 100g

**Cambios:**
- `app3/src/components/MenuApp.jsx`: Selector categorías gradiente→rojo sólido (`bg-red-600`). Eliminada flecha `<>` scroll. Scroll continuo entre categorías con IntersectionObserver + smooth scroll. Fix títulos debajo del header (`scroll-mt-[140px]`, `pt-[140px]`). Helper `getCategoryData()` para renderizar todas las categorías en secuencia.
- BD: `menu_categories` id=2 (Hamburguesas 100g) desactivada. id=1 renombrada "Hamburguesas (200g)" → "Hamburguesas".

**Commits:** `7991e02`, `00e32bc`, `24caa8e`, `de69ccc`, `fb99aa2`, `c6a19b2`, `5d6465e`
**Deploys:** app3 ✅ (`5d6465e`)
**BD:** Hamburguesas 100g desactivada, "Hamburguesas (200g)" → "Hamburguesas". 5 nuevas subcategorías bebidas (61-65): Aguas, Latas 350ml, Energéticas 473ml, Energéticas 250ml, Bebidas 1.5L. 43 productos reasignados.

**Commits:** `0723c72`
**Deploys:** app3 ✅, caja3 ✅ (ambos `0723c72`)
**BD:** Uniformización bebidas combos — 6 combos de lata ahora tienen las mismas 15 opciones 350ml. Doble Mixta 8→15, Completo 11→15, Gorda 11→15, Dupla 8→15, Hamburguesa Clásica 5→15, Salchipapa 4→15. Familiar sin cambios (1.5Lt).

---

> Sesiones anteriores (170+ total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Sesiones 2026-04-19c→2026-04-22b archivadas. Últimas: 2026-04-22a (spec combos-refactor fases 1-4), 2026-04-22b (fix pending pages + uniformizar bebidas combos).
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
