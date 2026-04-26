# Design: Acciones Masivas en Recetas + Sección Ventas

## Feature 1: Bulk Actions en Recetas

### Backend (mi3-backend)

**Nuevos endpoints:**

1. `PATCH /api/v1/admin/productos/toggle` — Toggle is_active
   - Body: `{ product_ids: number[] }`
   - Toggles is_active (1→0, 0→1) para cada producto
   - Returns: `{ success: true, toggled: number }`

2. `PATCH /api/v1/admin/productos/bulk-price` — Ajuste masivo de precio
   - Body: `{ product_ids: number[], adjustment: number }` (ej: +100, -100)
   - Valida que precio resultante > 0
   - Returns: `{ success: true, updated: number }`

3. `PATCH /api/v1/admin/productos/bulk-deactivate` — Desactivar masivo
   - Body: `{ product_ids: number[] }`
   - Sets is_active = 0
   - Returns: `{ success: true, deactivated: number }`

**Controller:** `ProductBulkController.php` en `Admin/`

### Frontend (mi3-frontend)

**Componente reutilizable:** `BulkActionBar.tsx`
- Props: `selectedIds`, `onClear`, `onToggle`, `onPriceAdjust`, `onDeactivate`
- Sticky bottom bar con acciones
- Responsive: full bar en desktop, compact en mobile

**Modificaciones a páginas existentes:**
- `recetas/page.tsx` — agregar checkbox column + BulkActionBar + toggle badge
- `recetas/bebidas/page.tsx` — agregar checkbox + BulkActionBar + toggle
- `recetas/combos/page.tsx` — agregar checkbox + BulkActionBar + toggle

## Feature 2: Sección Ventas

### Backend (mi3-backend)

**Nuevo controller:** `VentasController.php`

1. `GET /api/v1/admin/ventas` — Listado paginado
   - Query params: `period` (shift_today|today|week|month), `search`, `page`, `per_page`
   - Query optimizada sobre `tuu_orders` con JOIN a `tuu_order_items` para costos
   - Returns: paginado con items + totales

2. `GET /api/v1/admin/ventas/kpis` — KPIs agregados
   - Query params: `period`
   - Returns: `{ total_sales, total_cost, total_profit, order_count, by_payment_method }`

**Nuevo service:** `VentasService.php`
- Lógica de turnos: 17:30 → 04:00 del día siguiente (Chile time, UTC-3)
- Query eficiente con índices
- Cálculo de costos via tuu_order_items.item_cost

**Migración:** Verificar índice en `tuu_orders(created_at, payment_status)`

**Reverb broadcast:** Evento `VentaNueva` en canal `admin.ventas`
- Se dispara desde webhook de venta existente
- Payload: KPIs actualizados

### Frontend (mi3-frontend)

**Nuevo componente:** `VentasSection.tsx`
- Tabs: Turno Actual | Hoy | Semana | Mes
- KPI cards arriba (ventas, costo, utilidad, pedidos)
- Tabla de transacciones con búsqueda
- Desglose por método de pago (colapsable)
- Realtime via useAdminRealtime hook existente

**Nuevo page:** `app/admin/ventas/page.tsx`
- Componente principal con toda la lógica

### Estructura de archivos nuevos

```
mi3/backend/
  app/Http/Controllers/Admin/ProductBulkController.php
  app/Http/Controllers/Admin/VentasController.php
  app/Services/VentasService.php
  app/Events/VentaNueva.php

mi3/frontend/
  components/admin/sections/VentasSection.tsx
  app/admin/ventas/page.tsx
  components/admin/BulkActionBar.tsx
```
