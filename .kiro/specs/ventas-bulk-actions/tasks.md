# Tasks: Acciones Masivas en Recetas + Sección Ventas

## Feature 1: Bulk Actions

### Task 1: Backend — ProductBulkController
- [x] 1.1 Crear `mi3/backend/app/Http/Controllers/Admin/ProductBulkController.php`
  - Método `toggle(Request)`: recibe `product_ids[]`, toggle `is_active` con `DB::table('products')->whereIn(...)->update()`
  - Método `bulkPrice(Request)`: recibe `product_ids[]` + `adjustment` (int), actualiza `price = price + adjustment` donde `price + adjustment > 0`
  - Método `bulkDeactivate(Request)`: recibe `product_ids[]`, sets `is_active = 0`
  - Validación: `product_ids` required|array|min:1, cada id exists:products
- [x] 1.2 Registrar rutas en `routes/api.php`:
  - `PATCH admin/productos/toggle`
  - `PATCH admin/productos/bulk-price`
  - `PATCH admin/productos/bulk-deactivate`

### Task 2: Frontend — BulkActionBar componente reutilizable
- [x] 2.1 Crear `mi3/frontend/components/admin/BulkActionBar.tsx`
  - Props: `selectedCount: number`, `onClear()`, `onToggle()`, `onPriceAdjust(amount: number)`, `onDeactivate()`
  - UI: sticky bottom bar, bg-gray-900 text-white, rounded-t-xl
  - Botones: "X seleccionados" | +$100 | -$100 | input custom | Toggle ON/OFF | Eliminar
  - Mobile: compact con iconos, desktop: con labels
  - Animación: slide-up cuando selectedCount > 0

### Task 3: Frontend — Checkboxes + Toggle en Recetas (page.tsx)
- [x] 3.1 Agregar estado `selectedIds: Set<number>` en RecetasPage
- [x] 3.2 Agregar checkbox en header de tabla y en cada fila de producto
- [x] 3.3 Agregar badge ON/OFF clickable en cada fila (llama PATCH toggle con 1 id)
- [x] 3.4 Integrar BulkActionBar al bottom del listado
- [x] 3.5 Implementar handlers: handleBulkToggle, handleBulkPrice, handleBulkDeactivate
- [x] 3.6 Productos inactivos: opacity-50 + badge rojo "OFF"

### Task 4: Frontend — Checkboxes + Toggle en Bebidas y Combos
- [x] 4.1 Agregar checkboxes + toggle + BulkActionBar en `bebidas/page.tsx`
- [x] 4.2 Agregar checkboxes + toggle + BulkActionBar en `combos/page.tsx`

## Feature 2: Sección Ventas

### Task 5: Backend — VentasService + VentasController
- [x] 5.1 Crear `mi3/backend/app/Services/VentasService.php`
  - `getKpis(string $period)`: query agregada sobre tuu_orders con SUM, COUNT
  - `getTransactions(string $period, ?string $search, int $page, int $perPage)`: paginado
  - `getPaymentBreakdown(string $period)`: GROUP BY payment_method
  - Lógica de turnos: shift_today = 17:30 del día → 04:00 del siguiente (Chile UTC-3)
  - Queries usan índices: `tuu_orders(created_at, payment_status)`
- [x] 5.2 Crear `mi3/backend/app/Http/Controllers/Admin/VentasController.php`
  - `index(Request)` → getTransactions paginado
  - `kpis(Request)` → getKpis + getPaymentBreakdown
- [x] 5.3 Registrar rutas:
  - `GET admin/ventas` → index
  - `GET admin/ventas/kpis` → kpis

### Task 6: Backend — Evento Reverb para realtime
- [x] 6.1 Crear `mi3/backend/app/Events/VentaNueva.php` (broadcastOn: 'admin.ventas')
- [x] 6.2 Disparar evento desde `WebhookController::venta()` existente

### Task 7: Frontend — VentasSection + VentasPage
- [x] 7.1 Crear `mi3/frontend/components/admin/sections/VentasSection.tsx`
  - Tabs: Turno | Hoy | Semana | Mes
  - Registrar en AdminShell (SectionKey, SECTION_TITLES, sectionImports, sidebar)
- [x] 7.2 Crear `mi3/frontend/app/admin/ventas/page.tsx`
  - KPI cards: ventas, costo, utilidad, pedidos (4 cards grid)
  - Tabla transacciones: responsive, mobile cards / desktop table
  - Búsqueda + filtro período
  - Desglose método pago (colapsable)
  - Paginación
- [x] 7.3 Integrar realtime via Echo/Reverb (canal admin.ventas, evento VentaNueva)
  - Al recibir evento: refetch KPIs + prepend nueva transacción al listado

### Task 8: Registrar sección en AdminShell
- [x] 8.1 Agregar 'ventas' a SectionKey type, SECTION_TITLES, sectionImports
- [x] 8.2 Agregar icono en sidebar (Receipt) y mobile nav
