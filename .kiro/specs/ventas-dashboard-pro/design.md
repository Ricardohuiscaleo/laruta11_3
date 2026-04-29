# Ventas Dashboard Pro — Design

## Arquitectura General

### Layout Desktop (md+)
```
┌─────────────────────────────────────────────────────────┐
│ Header: Ventas | [Turno] [Hoy] [Semana] [Mes]          │
├────────────────────────┬────────────────────────────────┤
│ LEFT 50%               │ RIGHT 50%                      │
│                        │                                │
│ ┌────────────────────┐ │ ┌────────────────────────────┐ │
│ │ 🔴 Monitor Vivo    │ │ │ 📊 Barras Apiladas Mensual │ │
│ │ $45.250 · 5 pedidos│ │ │ (Recharts stacked bar)     │ │
│ │ ▸ Ver últimas      │ │ └────────────────────────────┘ │
│ └────────────────────┘ │                                │
│                        │ ┌────────────────────────────┐ │
│ ┌────────────────────┐ │ │ 🗺️ Mapa Concentración      │ │
│ │ ▸ Ventas Netas     │ │ │ (Leaflet + OSM heatmap)    │ │
│ │   $1.262.151 | 45% │ │ │ + zonas editables          │ │
│ └────────────────────┘ │ └────────────────────────────┘ │
│                        │                                │
│ ┌────────────────────┐ │ ┌────────────────────────────┐ │
│ │ ▸ CMV Ingredientes │ │ │ 📊 Top Productos           │ │
│ │   $690.113 | 54.7% │ │ │ (Horizontal bar chart)     │ │
│ └────────────────────┘ │ │ Toggle: vendidos/rentables  │ │
│                        │ └────────────────────────────┘ │
│ ┌────────────────────┐ │                                │
│ │ ▸ Nómina           │ │                                │
│ │   $XXX.XXX | N emp │ │                                │
│ └────────────────────┘ │                                │
├────────────────────────┴────────────────────────────────┤
│ ▸ Transacciones (full width, colapsable)                │
│   Lista paginada con detalle expandible                 │
└─────────────────────────────────────────────────────────┘
```

### Layout Mobile
```
┌──────────────────────┐
│ 🔴 Monitor Vivo      │
│ ▸ Ventas Netas (EdR) │
│ ▸ CMV Ingredientes   │
│ ▸ Nómina             │
│ 📊 Barras Mensual    │
│ 📊 Top Productos     │
│ 🗺️ Mapa              │
│ ▸ Transacciones      │
└──────────────────────┘
```

## Componentes Nuevos

### 1. `LiveSalesMonitor.tsx`
- Card con fondo gradiente rojo sutil
- Muestra: total turno, # pedidos, ticket promedio, indicador WS
- Chevron expande lista de últimas 5 ventas con slide-in animation
- Escucha `admin.ventas` → `.venta.nueva` via Echo
- Sonido toggle (localStorage para persistir preferencia)
- Counter animation con `requestAnimationFrame`

### 2. `PnLSection.tsx` (Estado de Resultados)
- Refactor del EdR existente en `DashboardSection.tsx` como componente colapsable
- Props: `title`, `summary`, `children`, `defaultOpen`
- Dentro: tabla EdR existente (ingresos, CMV, margen, OPEX, resultado) + sub-secciones método pago + top productos
- Datos del endpoint existente `GET /admin/dashboard`

### 3. `CmvSection.tsx` (Costo Ingredientes)
- Colapsable con tabla de ingredientes
- Nuevo endpoint: `GET /admin/ventas/cmv?period=X`
- Highlight ingredientes >10% del CMV

### 4. `PayrollSection.tsx` (Nómina en Ventas)
- Colapsable con resumen nómina
- Consume `GET /admin/payroll` existente
- Calcula % nómina sobre ventas

### 5. `MonthlyChart.tsx` (Barras Apiladas)
- Recharts `BarChart` con `stackId`
- 3 series: ventas (green-500), costo (red-400), delivery (blue-400)
- Tooltip formateado CLP
- Nuevo endpoint: `GET /admin/ventas/monthly?months=6`
- `dynamic(() => import(...), { ssr: false })` para lazy load

### 6. `TopProductsChart.tsx` (Horizontal Bar)
- Recharts `BarChart` layout="vertical"
- Toggle "Más vendidos" / "Más rentables"
- Barras segmentadas: costo (red) + utilidad (green)
- Label con margen %
- Nuevo endpoint: `GET /admin/ventas/top-products?period=X&limit=10`

### 7. `SalesHeatmap.tsx` (Mapa Leaflet)
- `react-leaflet` + `leaflet.heat` plugin para heatmap
- OpenStreetMap tiles (gratis, rápido)
- Puntos de entrega geocodificados desde `tuu_orders.delivery_lat/delivery_lng`
- Modo edición: dibujar polígonos con `leaflet-draw`
- Zonas guardadas en tabla `delivery_zones` (name, polygon JSON, color)
- `dynamic(() => import(...), { ssr: false })`

### 8. `CollapsibleSection.tsx` (Reutilizable)
- Props: `title: string`, `summary: ReactNode`, `children: ReactNode`, `defaultOpen?: boolean`
- Chevron animado con rotate transition
- `aria-expanded` para accesibilidad
- Borde izquierdo coloreado según tipo (verde ventas, rojo CMV, azul nómina)

## Endpoints Backend Nuevos

### `GET /admin/ventas/cmv?period=shift_today|today|week|month`
```json
{
  "success": true,
  "data": {
    "total_cmv": 690113.05,
    "cmv_percentage": 54.7,
    "ingredients": [
      {
        "ingredient_id": 13,
        "name": "Palta Hass",
        "total_quantity": 12.5,
        "unit": "kg",
        "total_cost": 87500,
        "percentage": 12.7
      }
    ]
  }
}
```
- Query: `inventory_transactions` JOIN `ingredients`, GROUP BY `ingredient_id`, filtrado por período y `transaction_type = 'sale'`
- Costo calculado: `quantity * unit_cost` del ingrediente (o del precio de compra promedio)

### `GET /admin/ventas/monthly?months=6`
```json
{
  "success": true,
  "data": [
    {
      "month": "2026-04",
      "label": "Abr",
      "total_sales": 1262151,
      "total_cost": 690113,
      "total_delivery": 106600
    }
  ]
}
```
- Query: `tuu_orders` GROUP BY `DATE_FORMAT(created_at, '%Y-%m')`, últimos N meses
- Excluye RL6-*

### `GET /admin/ventas/top-products?period=X&limit=10&sort=quantity|profit`
```json
{
  "success": true,
  "data": [
    {
      "product_name": "Completo Italiano",
      "quantity_sold": 45,
      "total_revenue": 112050,
      "total_cost": 79628,
      "total_profit": 32422,
      "margin_pct": 28.9
    }
  ]
}
```
- Query: `tuu_order_items` JOIN `tuu_orders`, GROUP BY `product_name`, filtrado por período
- Excluye RL6-*

## Dependencias Nuevas (frontend)

```bash
npm install recharts react-leaflet leaflet leaflet.heat @types/leaflet
```

## Tabla BD Nueva

### `delivery_zones`
```sql
CREATE TABLE delivery_zones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  polygon JSON NOT NULL,
  color VARCHAR(7) DEFAULT '#3B82F6',
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Realtime Flow

```
Caja3/App3 → create_order → tuu_orders INSERT
  → mi3-backend webhook → OrderStatusUpdated event
  → VentasService recalcula KPIs
  → broadcast(new VentaNueva($kpis, $order))
  → Canal: admin.ventas | Evento: .venta.nueva
  → Frontend: LiveSalesMonitor recibe → anima counter + slide-in
  → Frontend: PnLSection/Charts refetch datos
```

## Geocodificación de Direcciones

- Al crear orden delivery, geocodificar `delivery_address` y guardar `delivery_lat`/`delivery_lng` en `tuu_orders`
- Cache: si la dirección ya fue geocodificada antes, reusar coordenadas
- Usar Geocoding API de Google (ya tienen API key) o Nominatim (gratis, rate-limited)
- Para el mapa, solo mostrar órdenes que tengan lat/lng
