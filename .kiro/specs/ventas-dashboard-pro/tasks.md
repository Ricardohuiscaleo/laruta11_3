# Ventas Dashboard Pro — Tasks

## Task 1: CollapsibleSection component + Layout split
- [ ] Create `mi3/frontend/components/admin/ventas/CollapsibleSection.tsx` — reusable chevron section with `title`, `summary`, `children`, `defaultOpen`, `accentColor` props. Animated chevron rotation, `aria-expanded`, border-left accent color.
- [ ] Refactor `VentasPageContent.tsx` — split into 2-column grid on desktop (`md:grid-cols-2`), single column on mobile. Left column: data sections. Right column: charts/map (placeholder divs for now).
- [ ] Wrap existing KPI cards in a `CollapsibleSection` titled "Ventas Netas" with summary showing total sales + margin + order count.
- [ ] Move existing `PaymentBreakdownPanel` inside the "Ventas Netas" collapsible as a sub-section.
- [ ] Verify mobile layout stacks correctly with Estado de Resultados first.

## Task 2: LiveSalesMonitor component (realtime)
- [ ] Create `mi3/frontend/components/admin/ventas/LiveSalesMonitor.tsx` — card with gradient bg, shows shift total + pedidos + ticket promedio + WS indicator.
- [ ] Add chevron to expand last 5 sales with slide-in animation (CSS transition).
- [ ] Connect to Echo channel `admin.ventas` event `.venta.nueva` — on event: prepend sale to list, animate counter increment.
- [ ] Add sound toggle (🔔/🔕) persisted in localStorage. Play short notification sound on new sale.
- [ ] Place LiveSalesMonitor above everything (full width, both mobile and desktop).
- [ ] Counter animation: use `requestAnimationFrame` to smoothly animate number changes.

## Task 3: Estado de Resultados table (PnL)
- [ ] Inside "Ventas Netas" CollapsibleSection, add full P&L table: Ventas netas, + Delivery, = Ingreso bruto, − CMV, = Utilidad bruta, Margen %, Ticket promedio, Pedidos/día.
- [ ] Add "Top productos por ingreso" sub-table: product name, qty sold, total revenue, % of total. Requires data from new endpoint (Task 6).
- [ ] Style: compact rows, right-aligned numbers, bold totals, green/red for profit/loss.

## Task 4: CMV Ingredientes section
- [ ] Create `mi3/frontend/components/admin/ventas/CmvSection.tsx` — CollapsibleSection with summary "CMV $XXX | XX% sobre ventas".
- [ ] Table: ingredient name, qty consumed, unit, total cost, % of CMV. Sorted by cost desc.
- [ ] Highlight rows where ingredient >10% of total CMV (red-50 bg).
- [ ] Fetch from new endpoint `GET /admin/ventas/cmv?period=X` (Task 7).

## Task 5: Nómina section
- [ ] Create `mi3/frontend/components/admin/ventas/PayrollSummary.tsx` — CollapsibleSection with summary "Nómina $XXX | N trabajadores".
- [ ] Show: total sueldos, total adelantos, total créditos R11, nómina como % de ventas.
- [ ] Fetch from existing `GET /admin/payroll` endpoint, extract summary totals.

## Task 6: Backend — Top Products endpoint
- [ ] Add method `getTopProducts(string $period, int $limit, string $sort)` to `VentasService.php`.
- [ ] Query: `tuu_order_items` JOIN `tuu_orders` (paid, not RL6-*), GROUP BY `product_name`, SUM quantity/revenue/cost.
- [ ] Support sort by `quantity` (most sold) or `profit` (most profitable).
- [ ] Add route `GET /admin/ventas/top-products` in `VentasController.php`.

## Task 7: Backend — CMV Ingredients endpoint
- [ ] Add method `getCmvBreakdown(string $period)` to `VentasService.php`.
- [ ] Query: `inventory_transactions` (type=sale) JOIN `ingredients`, GROUP BY `ingredient_id`, filtered by period.
- [ ] Calculate cost per ingredient using `abs(quantity) * ingredient.cost_per_unit` or average purchase price.
- [ ] Return total CMV, CMV as % of sales, and per-ingredient breakdown.
- [ ] Add route `GET /admin/ventas/cmv` in `VentasController.php`.

## Task 8: Backend — Monthly aggregates endpoint
- [ ] Add method `getMonthlyAggregates(int $months)` to `VentasService.php`.
- [ ] Query: `tuu_orders` (paid, not RL6-*), GROUP BY `DATE_FORMAT(created_at, '%Y-%m')`, last N months.
- [ ] Return per-month: total_sales, total_cost, total_delivery, month label.
- [ ] Add route `GET /admin/ventas/monthly` in `VentasController.php`.

## Task 9: Install Recharts + Monthly Stacked Bar Chart
- [ ] Install recharts: `npm install recharts` in mi3/frontend.
- [ ] Create `mi3/frontend/components/admin/ventas/MonthlyChart.tsx` — Recharts `BarChart` stacked.
- [ ] 3 stacked series: Ventas (green-500), Costo (red-400), Delivery (blue-400).
- [ ] Tooltip with CLP formatting. Responsive container.
- [ ] Lazy load with `dynamic(() => import(...), { ssr: false })`.
- [ ] Place in right column (desktop) or after collapsible sections (mobile).
- [ ] Fetch from `GET /admin/ventas/monthly?months=6`.

## Task 10: Top Products Horizontal Bar Chart
- [ ] Create `mi3/frontend/components/admin/ventas/TopProductsChart.tsx` — Recharts horizontal `BarChart`.
- [ ] Each bar split: Costo (red) + Utilidad (green). Label with margin %.
- [ ] Toggle button: "Más vendidos" (sort by qty) / "Más rentables" (sort by profit).
- [ ] Lazy load with `dynamic(() => import(...), { ssr: false })`.
- [ ] Fetch from `GET /admin/ventas/top-products?period=X&limit=10&sort=quantity`.

## Task 11: Install Leaflet + Sales Heatmap
- [ ] Install: `npm install react-leaflet leaflet leaflet.heat @types/leaflet` in mi3/frontend.
- [ ] Create `mi3/frontend/components/admin/ventas/SalesHeatmap.tsx`.
- [ ] OpenStreetMap tiles, centered Arica (-18.4747, -70.2989), zoom 13.
- [ ] Heatmap layer from delivery coordinates (orders with `delivery_lat`/`delivery_lng`).
- [ ] Lazy load with `dynamic(() => import(...), { ssr: false })`.
- [ ] Place in right column (desktop) or after charts (mobile).
- [ ] Fetch delivery coordinates from new endpoint or extend existing orders endpoint.

## Task 12: Leaflet zone drawing + delivery_zones table
- [ ] Create migration for `delivery_zones` table (id, name, polygon JSON, color, is_active, timestamps).
- [ ] Add `leaflet-draw` plugin for polygon drawing mode in SalesHeatmap.
- [ ] Toggle "Editar zonas" button to enter/exit draw mode.
- [ ] On polygon save: POST to new endpoint `POST /admin/ventas/zones`.
- [ ] On load: GET existing zones and render as colored polygons on map.
- [ ] Backend: `DeliveryZoneController` with CRUD endpoints.

## Task 13: Realtime updates for all sections
- [ ] In `VentasPageContent.tsx`, on `.venta.nueva` event: refetch KPIs, CMV, top products.
- [ ] Add subtle animation to numbers that change (green flash + counter animation).
- [ ] LiveSalesMonitor already handles its own realtime (Task 2).
- [ ] Charts auto-refresh: MonthlyChart refetches on period change, TopProducts on event.
- [ ] Ensure no excessive API calls — debounce refetch to max 1 per 5 seconds.

## Task 14: Geocodificación delivery addresses
- [ ] Add `delivery_lat` and `delivery_lng` columns to `tuu_orders` (nullable decimal 10,7).
- [ ] In order creation flow (caja3/app3 webhook), geocode `delivery_address` using Google Geocoding API.
- [ ] Cache: check if same address was geocoded before (simple query on recent orders with same address).
- [ ] Backfill: script to geocode existing delivery orders that have address but no lat/lng.
- [ ] Endpoint to return delivery coordinates for heatmap: `GET /admin/ventas/delivery-points?period=X`.

## Task 15: Final integration + polish
- [ ] Wire all sections together in `VentasPageContent.tsx` with correct layout.
- [ ] Verify mobile layout: Monitor → EdR → CMV → Nómina → Charts → Map → Transacciones.
- [ ] Verify desktop layout: 50/50 split with data left, charts right.
- [ ] Performance: ensure lazy loading works, no layout shift on chart load.
- [ ] Accessibility: all collapsibles have `aria-expanded`, charts have `aria-label`, map has alt text.
- [ ] Build test: `npx next build` passes without errors.
