# Implementation Plan: Inventario Financiero Real

## Overview

Corrección del sistema financiero de La Ruta 11: migraciones DB para extender enums y agregar columnas JSON, servicio de cierre diario con cron, extensión del DashboardController con OPEX completo, auditoría + consumo en StockController, nuevo CapitalTrabajoController, y extensiones frontend en DashboardSection, ComprasSection (stock tab) y nuevo CapitalTrabajoSection.

Stack: PHP/Laravel (mi3-backend), TypeScript/Next.js (mi3-frontend), MySQL (shared DB).

## Tasks

- [x] 1. Database migrations — extend enums and add JSON columns
  - [x] 1.1 Create migration to extend `inventory_transactions.transaction_type` enum adding `consumption`
    - File: `mi3/backend/database/migrations/YYYY_MM_DD_000001_add_consumption_to_inventory_transactions.php`
    - ALTER TABLE `inventory_transactions` MODIFY COLUMN `transaction_type` ENUM('sale','purchase','adjustment','return','consumption')
    - _Requirements: 8.3_
  - [x] 1.2 Create migration to extend `compras.tipo_compra` enum with `gas`, `limpieza`, `packaging`, `servicios`
    - File: `mi3/backend/database/migrations/YYYY_MM_DD_000002_extend_tipo_compra_enum.php`
    - ALTER TABLE `compras` MODIFY COLUMN `tipo_compra` ENUM('ingredientes','insumos','equipamiento','otros','gas','limpieza','packaging','servicios') DEFAULT 'ingredientes'
    - _Requirements: 8.4, 3.1_
  - [x] 1.3 Create migration to add JSON desglose columns to `capital_trabajo`
    - File: `mi3/backend/database/migrations/YYYY_MM_DD_000003_add_desglose_to_capital_trabajo.php`
    - ADD COLUMN `desglose_ingresos` JSON NULL AFTER `ingresos_ventas`
    - ADD COLUMN `desglose_gastos` JSON NULL AFTER `egresos_gastos`
    - _Requirements: 5.2_
  - [x] 1.4 Create migration to reclassify existing purchases based on proveedor/ingredient category
    - File: `mi3/backend/database/migrations/YYYY_MM_DD_000004_reclassify_existing_purchases.php`
    - UPDATE compras with Abastible → gas, Limpieza ingredients → limpieza, Packaging → packaging, Servicios → servicios
    - Must run AFTER migrations 1.1 and 1.2
    - _Requirements: 8.1_

- [x] 2. Update Laravel models for new columns and casts
  - [x] 2.1 Update `CapitalTrabajo` model to include `desglose_ingresos` and `desglose_gastos` in fillable and casts
    - File: `mi3/backend/app/Models/CapitalTrabajo.php`
    - Add `desglose_ingresos`, `desglose_gastos` to `$fillable`
    - Add `'desglose_ingresos' => 'array'`, `'desglose_gastos' => 'array'` to `$casts`
    - _Requirements: 5.2, 4.1_

- [x] 3. Implement CierreDiarioService — core daily close logic
  - [x] 3.1 Create `CierreDiarioService` with `cerrar(string $fecha)` method
    - File: `mi3/backend/app/Services/CierreDiario/CierreDiarioService.php`
    - Calculate `saldo_inicial` from previous day's `saldo_final` (0 if no previous record, with warning in notas)
    - Calculate `ingresos_ventas` from `tuu_orders` with `payment_status='paid'` in turno range (17:00–04:00 UTC-3), using `installment_amount - delivery_fee`
    - Calculate `desglose_ingresos` by `payment_method` + manual cash from `caja_movimientos` tipo `ingreso` without `order_reference`
    - Calculate `egresos_compras` from `compras.monto_total` of the day
    - Calculate `egresos_gastos` from `inventory_transactions` tipo `consumption` + `caja_movimientos` tipo `retiro`
    - Calculate `desglose_gastos` with breakdown by consumption category + retiros
    - Persist via `updateOrCreate` on `fecha` (idempotent)
    - Return `['success' => bool, 'data' => CapitalTrabajo, 'warnings' => string[]]`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.8, 5.1, 5.2, 5.3, 5.4_
  - [x] 3.2 Add `getResumenMensual(string $mes)` method to CierreDiarioService
    - Query `capital_trabajo` for all days in the month, ordered by fecha
    - Calculate totals: total ingresos, total egresos_compras, total egresos_gastos, variación neta
    - Mark missing days as "Sin cierre"
    - Return `['dias' => [...], 'totales' => [...]]`
    - _Requirements: 7.1, 7.2, 7.3, 7.4_
  - [ ]* 3.3 Write property test: Daily close chain invariant (Property 9)
    - **Property 9: Daily close chain invariant**
    - For any sequence of consecutive daily closes, day N's `saldo_inicial` equals day (N−1)'s `saldo_final`; if no previous record, `saldo_inicial` = 0
    - **Validates: Requirements 4.2, 4.8**
  - [ ]* 3.4 Write property test: Daily close accounting equation (Property 10)
    - **Property 10: Daily close accounting equation**
    - For any daily close: `saldo_final = saldo_inicial + ingresos_ventas − egresos_compras − egresos_gastos` must hold exactly
    - **Validates: Requirements 4.3, 4.4, 4.5, 4.6**
  - [ ]* 3.5 Write property test: Income includes all paid orders regardless of payment method (Property 11)
    - **Property 11: Income includes all paid orders regardless of payment method**
    - For any set of orders with various `payment_method` values, `ingresos_ventas` includes all where `payment_status='paid'` and excludes all others
    - **Validates: Requirements 5.1, 5.4**
  - [ ]* 3.6 Write property test: Income breakdown partition (Property 12)
    - **Property 12: Income breakdown partition**
    - Sum of all values in `desglose_ingresos` equals `ingresos_ventas`
    - **Validates: Requirements 5.2**

- [x] 4. Checkpoint — Ensure migrations and CierreDiarioService tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Extend StockController — audit + consumption with validation
  - [x] 5.1 Modify `StockController::consumir()` to use `transaction_type = 'consumption'` and validate stock >= cantidad
    - File: `mi3/backend/app/Http/Controllers/Admin/StockController.php`
    - Change `'transaction_type' => 'adjustment'` to `'transaction_type' => 'consumption'`
    - Add validation: if `current_stock < cantidad`, return 422 with "Stock insuficiente: disponible X {unit}"
    - Record cost = cantidad × cost_per_unit in transaction notes or as gasto operacional
    - _Requirements: 2.2, 2.3, 2.4, 2.5_
  - [x] 5.2 Add `auditoria()` method to StockController
    - POST `/api/v1/admin/stock/auditoria`
    - Body: `{ items: [{ ingredient_id: int, physical_count: float }] }`
    - Wrap in DB transaction
    - For each item: calculate diff, update `current_stock`, create `adjustment` transaction with notes "Auditoría física"
    - Recalculate `stock_quantity` for all products with recipes using adjusted ingredients (g→kg conversion)
    - Return summary: items_modified, valor_antes, valor_despues, diferencia, warnings for invalid ingredient_ids
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_
  - [x] 5.3 Add `consumibles()` method to StockController for listing consumable ingredients
    - GET `/api/v1/admin/stock/consumibles`
    - Return ingredients where `is_active = 1` AND `category IN ('Gas', 'Limpieza', 'Servicios')`
    - Include: name, current_stock, unit, cost_per_unit, valor_inventario (stock × cost)
    - _Requirements: 2.1, 2.6_
  - [x] 5.4 Register new routes in `api.php`
    - Add `POST stock/auditoria` → `StockController::auditoria`
    - Add `GET stock/consumibles` → `StockController::consumibles`
    - _Requirements: 1.1, 2.1_
  - [ ]* 5.5 Write property test: Audit round-trip preserves physical counts (Property 1)
    - **Property 1: Audit round-trip preserves physical counts**
    - For any set of ingredients with arbitrary stock and physical counts, after audit `current_stock` equals submitted physical count
    - **Validates: Requirements 1.2, 1.3**
  - [ ]* 5.6 Write property test: Audit trail completeness (Property 2)
    - **Property 2: Audit trail completeness**
    - For N ingredients with diff, exactly N `adjustment` transactions created with correct `previous_stock` and `new_stock`
    - **Validates: Requirements 1.4**
  - [ ]* 5.7 Write property test: Product stock derivation from recipe (Property 3)
    - **Property 3: Product stock derivation from recipe**
    - After audit, product `stock_quantity` = `floor(min(ingredient.current_stock / recipe.quantity))` with g→kg conversion
    - **Validates: Requirements 1.5**
  - [ ]* 5.8 Write property test: Audit summary correctness (Property 4)
    - **Property 4: Audit summary correctness**
    - Summary reports correct items_modified, valor_antes, valor_despues, diferencia
    - **Validates: Requirements 1.6**
  - [ ]* 5.9 Write property test: Consumable filter correctness (Property 5)
    - **Property 5: Consumable filter correctness**
    - Consumables list contains exactly ingredients where `is_active=1` AND `category IN ('Gas','Limpieza','Servicios')`
    - **Validates: Requirements 2.1**
  - [ ]* 5.10 Write property test: Consumption reduces stock and records transaction (Property 6)
    - **Property 6: Consumption reduces stock and records transaction with correct cost**
    - After consumption: `current_stock = S − C`, transaction has `quantity = -C`, `previous_stock = S`, `new_stock = S − C`, cost = C × cost_per_unit
    - **Validates: Requirements 2.2, 2.3, 2.4**
  - [ ]* 5.11 Write property test: Consumption rejection when stock insufficient (Property 7)
    - **Property 7: Consumption rejection when stock insufficient**
    - When C > S, operation rejected and `current_stock` remains S
    - **Validates: Requirements 2.5**

- [x] 6. Extend CompraController — auto-classification of tipo_compra
  - [x] 6.1 Modify `CompraController::store()` to auto-classify `tipo_compra` based on ingredient category
    - File: `mi3/backend/app/Http/Controllers/Admin/CompraController.php`
    - Add constant `CATEGORY_TO_TIPO_COMPRA`: Gas→gas, Limpieza→limpieza, Packaging→packaging, Servicios→servicios
    - When compra has ingredient items, derive `tipo_compra` from ingredient category
    - When no ingredient associated, allow manual selection from extended list
    - Maintain backward compatibility with existing categories
    - _Requirements: 3.2, 3.3, 3.4_
  - [ ]* 6.2 Write property test: Purchase auto-classification (Property 8)
    - **Property 8: Purchase auto-classification from ingredient category**
    - For any purchase with ingredient items, `tipo_compra` matches the category mapping
    - **Validates: Requirements 3.2**

- [x] 7. Checkpoint — Ensure StockController and CompraController tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Extend DashboardController — full OPEX in P&L
  - [x] 8.1 Modify `DashboardController::index()` to calculate OPEX lines from DB
    - File: `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`
    - Replace HTTP call to `get_sales_analytics.php` for CMV with direct query to `tuu_order_items` (sum `item_cost × quantity` for paid orders of the month)
    - Add gas line: sum cost of `inventory_transactions` tipo `consumption` where ingredient category = 'Gas' for the month
    - Add limpieza line: same for category 'Limpieza'
    - Add mermas line: sum `mermas.cost` for the month
    - Add otros_gastos line: sum consumption for category 'Servicios'
    - Update `total_opex` = nomina + gas + limpieza + mermas + otros_gastos
    - Update `resultado_neto` = margen_bruto − total_opex
    - Add percentage calculation for each OPEX line: `round((value / ventas_netas) * 100, 1)`
    - Add `meta_equilibrio` = total_opex / (margen_bruto_pct / 100) when margen_bruto_pct > 0
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8_
  - [ ]* 8.2 Write property test: P&L accounting equation (Property 13)
    - **Property 13: P&L accounting equation**
    - `resultado_neto = ingresos_netos − cmv − total_opex`
    - **Validates: Requirements 6.1, 6.2, 6.3, 6.5, 6.6**
  - [ ]* 8.3 Write property test: P&L OPEX completeness (Property 14)
    - **Property 14: P&L OPEX completeness**
    - OPEX includes all categories and `total_opex` = sum of individual lines
    - **Validates: Requirements 6.4, 6.5**
  - [ ]* 8.4 Write property test: P&L percentage calculation (Property 15)
    - **Property 15: P&L percentage calculation**
    - For each line with value V and ingresos I > 0, percentage = `round((V / I) × 100, 1)`
    - **Validates: Requirements 6.7**

- [x] 9. Create CapitalTrabajoController + routes
  - [x] 9.1 Create `CapitalTrabajoController` with `resumenMensual` and `cierreManual` methods
    - File: `mi3/backend/app/Http/Controllers/Admin/CapitalTrabajoController.php`
    - `resumenMensual(Request $request)`: GET `/api/v1/admin/capital-trabajo?mes=2026-04`, delegates to `CierreDiarioService::getResumenMensual`
    - `cierreManual(Request $request)`: POST `/api/v1/admin/capital-trabajo/cierre` with `{ fecha: 'YYYY-MM-DD' }`, delegates to `CierreDiarioService::cerrar`
    - _Requirements: 4.7, 7.1, 7.2, 7.3, 7.4_
  - [x] 9.2 Register routes in `api.php`
    - Add `GET admin/capital-trabajo` → `CapitalTrabajoController::resumenMensual`
    - Add `POST admin/capital-trabajo/cierre` → `CapitalTrabajoController::cierreManual`
    - _Requirements: 4.7, 7.1_

- [x] 10. Create CierreDiarioCommand + recalculate historic command + cron registration
  - [x] 10.1 Create `CierreDiarioCommand` artisan command
    - File: `mi3/backend/app/Console/Commands/CierreDiarioCommand.php`
    - Signature: `mi3:cierre-diario`
    - Calculates fecha del turno que acaba de terminar (yesterday at 04:15)
    - Calls `CierreDiarioService::cerrar($fecha)`
    - Logs result
    - _Requirements: 4.1_
  - [x] 10.2 Create `RecalcularHistoricoCommand` artisan command
    - File: `mi3/backend/app/Console/Commands/RecalcularHistoricoCommand.php`
    - Signature: `mi3:cierre-recalcular-historico`
    - Gets earliest date from `tuu_orders`, iterates day by day calling `CierreDiarioService::cerrar($fecha)`
    - Chains `saldo_final` → `saldo_inicial` of next day
    - _Requirements: 8.2_
  - [x] 10.3 Register `mi3:cierre-diario` in `console.php` scheduler
    - Add entry to `$commands` array: `dailyAt('07:15')` (04:15 Chile UTC-3), timezone America/Santiago
    - _Requirements: 4.1_

- [x] 11. Checkpoint — Ensure all backend tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 12. Extend DashboardSection.tsx — show full OPEX in P&L table
  - [x] 12.1 Update DashboardSection to render new OPEX lines
    - File: `mi3/frontend/components/admin/sections/DashboardSection.tsx`
    - Add rows for Gas, Limpieza, Mermas, Otros Gastos in the P&L table under Gastos Operacionales
    - Show percentage column for each line (value / ventas_netas × 100)
    - Add "Meta Mensual (Punto de Equilibrio)" line
    - _Requirements: 6.4, 6.7, 6.8_

- [x] 13. Extend ComprasSection stock tab — add Consumibles panel and Auditoría panel
  - [x] 13.1 Add Consumibles panel to the Stock tab
    - File: `mi3/frontend/app/admin/compras/stock/page.tsx` (or new component within)
    - List consumable ingredients (from GET `/api/v1/admin/stock/consumibles`)
    - Show: name, stock actual, unit, costo unitario, valor inventario
    - "Consumir" button opens modal with quantity input
    - Client-side validation: cantidad <= stock disponible
    - POST to `/api/v1/admin/stock/consumir` on confirm
    - _Requirements: 2.1, 2.2, 2.6_
  - [x] 13.2 Add Auditoría panel to the Stock tab
    - Add "Auditoría Física" button that shows list of active ingredients with system stock
    - Editable input for "Conteo Físico" per ingredient
    - Preview differences before confirming
    - "Aplicar Auditoría" button POSTs to `/api/v1/admin/stock/auditoria`
    - Show post-audit summary: items modified, valor antes/después, diferencia
    - _Requirements: 1.1, 1.2, 1.3, 1.6_

- [x] 14. Create CapitalTrabajoSection.tsx — monthly capital view
  - [x] 14.1 Create `CapitalTrabajoSection.tsx` component
    - File: `mi3/frontend/components/admin/sections/CapitalTrabajoSection.tsx`
    - Month selector (input type="month")
    - Table: Fecha | Saldo Inicial | Ingresos | Compras | Gastos | Saldo Final
    - Totals row at bottom
    - Days without cierre marked with "Sin cierre" badge
    - "Cerrar día" button for manual close (POST to `/api/v1/admin/capital-trabajo/cierre`)
    - Fetch from GET `/api/v1/admin/capital-trabajo?mes=YYYY-MM`
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 4.7_
  - [x] 14.2 Register `capital` section in AdminShell.tsx
    - Add `'capital'` to `SectionKey` union type
    - Add entry in `SECTION_TITLES`: `capital: 'Capital de Trabajo'`
    - Add lazy import in `sectionImports`: `capital: lazy(() => import(...))`
    - Add sidebar entry in `AdminSidebarSPA.tsx` and `MobileBottomNavSPA.tsx`
    - _Requirements: 7.1_
  - [ ]* 14.3 Write property test: Monthly totals partition (Property 16)
    - **Property 16: Monthly totals partition**
    - Monthly total for each column equals sum of daily values
    - Test in frontend with fast-check against mock data
    - **Validates: Requirements 7.3**

- [x] 15. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests (Properties 1–16) validate universal correctness properties from the design document
- Migrations must run in order (1.1 → 1.2 → 1.3 → 1.4) since reclassification depends on extended enums
- The `RecalcularHistoricoCommand` (10.2) should be run manually after deployment, not as a scheduled cron
- Frontend components use the existing lazy-loading pattern from AdminShell
- Stock tab lives inside ComprasSection as a sub-tab, not as a standalone section
