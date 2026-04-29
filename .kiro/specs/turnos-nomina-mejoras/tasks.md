# Tasks — Turnos y Nómina: Mejoras

## Task 1: Exponer campos de reemplazo en LiquidacionService

- [x] 1.1 Agregar `total_reemplazando` y `total_reemplazados` al array de retorno de `LiquidacionService::calcular()`
  - File: `mi3/backend/app/Services/Payroll/LiquidacionService.php`
  - Add `'total_reemplazando' => (int) round($totalReemplazando)` and `'total_reemplazados' => (int) round($totalReemplazados)` to the return array, before the `'total'` key
  - These variables already exist in the method (lines 83-91), they just need to be included in the return

- [ ] 1.2 Write property test: gran_total invariant
  - File: `mi3/backend/tests/Unit/Payroll/LiquidacionReplacementPropertyTest.php`
  - Generate random sueldo_base, replacement amounts, and ajustes using Faker
  - Verify: `total == sueldo_base + total_reemplazando - total_reemplazados + total_ajustes` for 100 iterations
  - Test the calcular() method directly with mocked shifts and adjustments

## Task 2: Exponer desglose de reemplazos en PayrollController

- [x] 2.1 Extend `PayrollController::index()` to include replacement breakdown fields in `$workersMap`
  - File: `mi3/backend/app/Http/Controllers/Admin/PayrollController.php`
  - Add fields to the initial worker entry: `total_reemplazando`, `total_reemplazado`, `reemplazos_realizados`, `reemplazos_recibidos`
  - In the aggregation block (where `isset($workersMap[$pid])`), sum `total_reemplazando` and `total_reemplazado`, and merge the `reemplazos_realizados` and `reemplazos_recibidos` arrays
  - Map `$liq['total_reemplazados']` (with 's') to response field `total_reemplazado` (without 's')
  - Recalculate `gran_total` as `sueldo_base + total_reemplazando - total_reemplazado + ajustes_total`

- [ ] 2.2 Write property test: aggregation consistency across cost centers
  - File: `mi3/backend/tests/Unit/Payroll/PayrollAggregationPropertyTest.php`
  - Generate workers with random replacement data in both ruta11 and seguridad contexts
  - Verify: aggregated `total_reemplazando == ruta11.total_reemplazando + seguridad.total_reemplazando`
  - Verify: aggregated `total_reemplazado == ruta11.total_reemplazados + seguridad.total_reemplazados`
  - Run 100 iterations with Faker

## Task 3: Agregar crédito R11 pendiente a la respuesta de nómina

- [x] 3.1 Add R11 credit pending calculation to `PayrollController::index()`
  - File: `mi3/backend/app/Http/Controllers/Admin/PayrollController.php`
  - After the cost center aggregation loop, iterate over `$workersMap`
  - For each worker, load `Personal::find($pid)` to get `user_id`
  - Query `usuarios` table: if `es_credito_r11 = 1` and `credito_r11_usado > 0`
  - Check if deduction already applied: `AjusteSueldo::where('personal_id', $pid)->where('mes', $mesDate)->whereHas('categoria', fn($q) => $q->where('slug', 'descuento_credito_r11'))->exists()`
  - Set `credito_r11_pendiente` to 0 if already deducted, otherwise to `credito_r11_usado`
  - Only include the field if the worker has an R11 credit user linked

- [ ] 3.2 Write edge-case test: R11 credit pending is 0 when already deducted
  - File: `mi3/backend/tests/Unit/Payroll/R11CreditPendingTest.php`
  - Create a personal record linked to a usuario with `es_credito_r11 = 1` and `credito_r11_usado = 5000`
  - Create an `ajustes_sueldo` record with `descuento_credito_r11` category for the current month
  - Verify that `credito_r11_pendiente` is 0 in the API response
  - Test without the adjustment record and verify `credito_r11_pendiente` is 5000

## Task 4: NominaSection — Tarjetas con desglose de reemplazos y expansión

- [x] 4.1 Update `WorkerPayroll` interface and add `ReplacementGroup` interface
  - File: `mi3/frontend/components/admin/sections/NominaSection.tsx`
  - Add to `WorkerPayroll`: `total_reemplazando: number`, `total_reemplazado: number`, `reemplazos_realizados: ReplacementGroup[]`, `reemplazos_recibidos: ReplacementGroup[]`, `credito_r11_pendiente?: number`
  - Add new interface `ReplacementGroup`: `{ personal_id: number; nombre: string; dias: number[]; monto: number; pago_por: string }`

- [x] 4.2 Add compact replacement display to worker cards
  - File: `mi3/frontend/components/admin/sections/NominaSection.tsx`
  - Change grid from `grid-cols-4` to `grid-cols-3 sm:grid-cols-5` to accommodate new fields on larger screens
  - Add `+Reemp` column in green (`text-green-600`) showing `+formatCLP(total_reemplazando)` when > 0
  - Add `-Reemp` column in red (`text-red-600`) showing `-formatCLP(total_reemplazado)` when > 0
  - Keep Base, Días, Ajustes columns

- [x] 4.3 Add expandable card with full breakdown
  - File: `mi3/frontend/components/admin/sections/NominaSection.tsx`
  - Add state: `const [expandedId, setExpandedId] = useState<number | null>(null)`
  - Wrap card in a clickable container that toggles `expandedId`
  - When expanded, show below the compact grid:
    - Sueldo base row
    - Reemplazos realizados: for each group, show "→ {nombre}: días {dias.join(', ')} — +{formatCLP(monto)}" in green
    - Reemplazos recibidos: for each group, show "← {nombre}: días {dias.join(', ')} — -{formatCLP(monto)}" in red
    - Ajustes: list each with concepto and monto
    - Crédito R11 pendiente (if > 0): "💳 Crédito R11 pendiente: {formatCLP(amount)} — Se descontará el día 1" in gray
  - Add ChevronDown/ChevronUp icon to indicate expandable state
  - Use `aria-expanded` attribute for accessibility

- [x] 4.4 Add R11 credit pending indicator below gran_total
  - File: `mi3/frontend/components/admin/sections/NominaSection.tsx`
  - Below the `gran_total` display, when `credito_r11_pendiente > 0`:
  - Show: "💳 Crédito R11: -{formatCLP(credito_r11_pendiente)}" in `text-xs text-gray-500`
  - Add tooltip or small text: "Se descontará el día 1"

## Task 5: TurnosSection — Calendario compacto con avatares

- [x] 5.1 Reduce calendar cell size and add mini avatars in desktop view
  - File: `mi3/frontend/components/admin/sections/TurnosSection.tsx`
  - Change `min-h-[100px]` to `min-h-[72px]` on calendar grid cells
  - Change day number from `text-3xl` to `text-lg`
  - Inside each cell, after the day number, render mini avatars (24px circles) for workers on that day
  - Group avatars: R11 workers first, then a thin separator `|`, then Seguridad workers
  - Use `workerPhotos` for avatar images, fall back to initials (first letter of nombre)
  - Limit to max 5 visible avatars per cell, show `+N` overflow indicator if more

- [x] 5.2 Add replacement indicator to calendar cells
  - File: `mi3/frontend/components/admin/sections/TurnosSection.tsx`
  - For each day cell, check if any turno in `turnosByDate[dateStr]` has `reemplazado_por` set
  - If yes, add `border-l-[3px] border-orange-400` to the cell className
  - This provides a visual orange left border for days with replacements

- [x] 5.3 Add replacement indicator to mobile day cards
  - File: `mi3/frontend/components/admin/sections/TurnosSection.tsx`
  - In the mobile horizontal scroll view, for each day button:
  - Check if any turno for that day has `reemplazado_por` set
  - If yes, render a small orange dot (`w-1.5 h-1.5 rounded-full bg-orange-400`) below the count span

- [x] 5.4 Improve replacement display in day detail panel
  - File: `mi3/frontend/components/admin/sections/TurnosSection.tsx`
  - In the selected day detail panel, for turnos with `reemplazado_por`:
  - Show the titular name with `line-through text-gray-400` style
  - Show arrow `→` separator
  - Show the replacer name in normal style
  - Show monto in `text-xs text-gray-500`: `formatCLP(monto_reemplazo)`
  - This replaces the current display that just shows the replacer avatar without context
complee