# Fix Nómina & EdR Bugs — Bugfix Design

## Overview

El sistema de nómina y Estado de Resultados presenta 4 bugs interrelacionados: los crons de descuento automático (`mi3:r11-auto-deduct` y `mi3:loan-auto-deduct`) asignan ajustes salariales al mes incorrecto, el EdR muestra $0 en nómina para meses pasados porque el fallback a `NominaService` está restringido al mes actual, y el componente `NominaSection` provoca un loop infinito de re-renders (React Error #185) por referencias JSX inestables en `trailing`.

La estrategia de fix es quirúrgica: cambiar `now()` por `now()->subMonth()` en los dos crons, eliminar el guard `$isCurrentMonth` del fallback de nómina en `DashboardController`, y estabilizar el `trailing` JSX con `useMemo` en `NominaSection`.

## Glossary

- **Bug_Condition (C)**: Conjunto de condiciones que disparan cada bug — mes incorrecto en crons, guard restrictivo en EdR, referencia JSX inestable en NominaSection
- **Property (P)**: Comportamiento correcto esperado — descuentos atribuidos al mes anterior, nómina visible para cualquier mes, sin loops de re-render
- **Preservation**: Comportamiento existente que no debe cambiar — lógica de selección de deudores, cálculo de liquidaciones, flujo de aprobación de adelantos, ventas en tiempo real del EdR
- **`autoDeduct()`**: Método en `R11CreditService.php` que descuenta crédito R11 de la nómina mensualmente
- **`procesarDescuentosMensuales()`**: Método en `LoanService.php` que descuenta adelantos de sueldo mensualmente
- **`DashboardController::index()`**: Endpoint que genera el Estado de Resultados (EdR) con P&L completo
- **`NominaService::getResumen()`**: Servicio que calcula nómina desde turnos y contratos (fuente de verdad cuando no hay `pagos_nomina`)
- **`setHeaderConfigs`**: State setter en `AdminShell` que almacena configuración de header por sección

## Bug Details

### Bug Condition

Los 4 bugs se manifiestan en contextos distintos pero comparten un patrón: uso incorrecto de `now()` como referencia temporal, un guard condicional demasiado restrictivo, y una referencia de objeto inestable en React.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type { context: 'cron_r11' | 'cron_loan' | 'edr_nomina' | 'react_header', params: any }
  OUTPUT: boolean

  IF input.context = 'cron_r11' THEN
    // Bug 1: R11 auto-deduct uses current month instead of previous
    RETURN input.params.cronRunDate.day = 1
           AND R11CreditService.autoDeduct() uses now().format('Y-m') for mes
           AND the credit was consumed in the PREVIOUS month

  ELSE IF input.context = 'cron_loan' THEN
    // Bug 2: Loan auto-deduct uses current month instead of previous
    RETURN input.params.cronRunDate.day = 1
           AND LoanService.procesarDescuentosMensuales() uses now().format('Y-m') for mes
           AND the loan was approved in the PREVIOUS month

  ELSE IF input.context = 'edr_nomina' THEN
    // Bug 3: EdR nomina fallback restricted to current month
    RETURN input.params.requestedMonth != currentMonth
           AND pagos_nomina for requestedMonth is empty
           AND compras with tipo_compra='nomina' for requestedMonth is empty
           AND NominaService fallback is skipped due to $isCurrentMonth guard

  ELSE IF input.context = 'react_header' THEN
    // Bug 4: Trailing JSX creates new reference on every render
    RETURN NominaSection.useEffect calls onHeaderConfig
           AND trailing JSX is created inline (new object reference each render)
           AND AdminShell.setHeaderConfigs detects "change" via reference inequality
           AND this triggers state update → re-render → new trailing → infinite loop

  END IF
END FUNCTION
```

### Examples

- **Bug 1**: El 1 de mayo a las 06:00, `mi3:r11-auto-deduct` crea `AjusteSueldo` con `mes = '2026-05-01'` y concepto "mayo", cuando debería ser `mes = '2026-04-01'` y concepto "abril" (el crédito se consumió en abril)
- **Bug 2**: El 1 de mayo a las 06:30, `mi3:loan-auto-deduct` crea `AjusteSueldo` con `mes = '2026-05-01'` y concepto "mayo", cuando debería ser `mes = '2026-04-01'` y concepto "abril"
- **Bug 3**: Admin navega al EdR de abril (estando en mayo). No hay registros en `pagos_nomina` ni `compras` para abril. El sistema muestra $0 en nómina porque el fallback `NominaService` solo se ejecuta cuando `$isCurrentMonth === true`
- **Bug 4**: Al cargar el dashboard admin, `NominaSection` llama `onHeaderConfig` con un objeto `trailing` JSX nuevo en cada render. `AdminShell.setHeaderConfigs` compara por referencia (`existing.trailing === config.trailing`), detecta "cambio", actualiza state, provoca re-render de `NominaSection`, que vuelve a llamar `onHeaderConfig` → loop infinito → React Error #185

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- La lógica de selección de deudores R11 (`es_credito_r11 = 1`, `credito_r11_usado > 0`, personal activo) debe seguir igual
- La creación de `R11CreditTransaction` refund y reset de `credito_r11_usado` a 0 debe seguir igual
- La selección de préstamos aprobados con cuotas pendientes y `fecha_inicio_descuento <= startOfMonth` debe seguir igual
- El marcado de préstamos como `pagado` tras el descuento debe seguir igual
- La aprobación de adelantos mid-month (`aprobar()`) debe seguir creando el ajuste con el mes actual (es un ajuste inmediato, no del cron)
- El EdR del mes actual debe seguir obteniendo ventas en tiempo real de `caja.laruta11.cl`
- Cuando existen registros en `pagos_nomina`, estos deben seguir siendo la fuente primaria de nómina
- El cálculo de CMV desde `VentasService` debe seguir igual
- Las secciones lazy-loaded, error boundaries, WebSocket y SPA navigation deben seguir funcionando
- Los 11 cron commands registrados deben seguir logueando en `cron_executions` y alertando por Telegram en fallo

**Scope:**
Todos los inputs que NO involucran: (a) la variable `$mes` en `autoDeduct()` y `procesarDescuentosMensuales()`, (b) el guard `$isCurrentMonth` en el fallback de nómina del `DashboardController`, o (c) la referencia del `trailing` JSX en `NominaSection`, deben ser completamente inalterados por este fix.

## Hypothesized Root Cause

Based on code analysis, the root causes are confirmed:

1. **Bug 1 — `R11CreditService::autoDeduct()` línea `$mes = now()->format('Y-m')`**: El cron corre el día 1 del mes M. `now()` retorna M, pero el crédito fue consumido durante M-1. La variable `$mes` se usa para `AjusteSueldo.mes` y para el nombre del mes en `concepto`. Ambos quedan asignados al mes incorrecto.

2. **Bug 2 — `LoanService::procesarDescuentosMensuales()` línea `$mes = now()->format('Y-m')`**: Mismo patrón que Bug 1. El cron corre el día 1 del mes M, pero el adelanto fue aprobado y debe descontarse del mes M-1. La variable `$mes` y `$mesNombre` quedan con el mes actual en vez del anterior.

3. **Bug 3 — `DashboardController::index()` guard `if ($totalNomina === 0.0 && $isCurrentMonth)`**: El fallback a `NominaService::getResumen()` solo se ejecuta cuando `$isCurrentMonth` es true. Para meses pasados sin registros en `pagos_nomina` ni `compras`, el sistema retorna $0 en nómina. La condición `$isCurrentMonth` es innecesaria — `NominaService` puede calcular nómina para cualquier mes basándose en turnos y contratos.

4. **Bug 4 — `NominaSection.tsx` useEffect con `trailing` JSX inline**: El `useEffect` que llama `onHeaderConfig` incluye `data` en su dependency array. Dentro del effect, se crea un JSX element `trailing` inline. Cada vez que el effect corre, `trailing` es un nuevo objeto. En `AdminShell`, `setHeaderConfigs` compara `existing.trailing === config.trailing` por referencia. Como siempre es un objeto nuevo, siempre detecta "cambio", actualiza state, provoca re-render de `NominaSection` (porque `data` cambia de referencia al re-fetch o porque el parent re-renderiza), y el cycle se repite infinitamente.

## Correctness Properties

Property 1: Bug Condition - Cron Deductions Use Previous Month

_For any_ execution of `autoDeduct()` or `procesarDescuentosMensuales()` on the 1st of month M, the created `AjusteSueldo` records SHALL have `mes = M-1` (previous month) and the `concepto` string SHALL reference the previous month's name, correctly attributing the deduction to the month when the credit/loan was consumed.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

Property 2: Bug Condition - EdR Nomina Fallback Works for All Months

_For any_ request to the EdR dashboard for month X where `pagos_nomina` and `compras` with `tipo_compra='nomina'` are both empty, the system SHALL use `NominaService::getResumen(X)` as fallback to calculate nómina cost, regardless of whether X is the current month or a historical month.

**Validates: Requirements 2.5, 2.6**

Property 3: Bug Condition - NominaSection Header Config Stability

_For any_ render cycle of `NominaSection`, the `trailing` JSX passed to `onHeaderConfig` SHALL maintain referential stability (same object reference) when the underlying data has not changed, preventing infinite re-render loops in `AdminShell`.

**Validates: Requirements 2.7**

Property 4: Preservation - Existing Cron Logic Unchanged

_For any_ execution of `autoDeduct()` or `procesarDescuentosMensuales()`, the system SHALL produce the same debtor selection, transaction creation, credit reset, and loan status updates as the original code, preserving all business logic except the month attribution.

**Validates: Requirements 3.1, 3.2, 3.3**

Property 5: Preservation - EdR Non-Nomina Data Unchanged

_For any_ request to the EdR dashboard, the system SHALL produce the same ventas, CMV, OPEX, and meta calculations as the original code, preserving all P&L logic except the nómina fallback guard.

**Validates: Requirements 3.4, 3.5, 3.6**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `mi3/backend/app/Services/Credit/R11CreditService.php`

**Function**: `autoDeduct()`

**Specific Changes**:
1. **Change month reference**: Replace `$mes = now()->format('Y-m')` with `$mes = now()->subMonth()->format('Y-m')` so the deduction is attributed to the previous month
2. **Change month name**: Replace `$mesNombre = now()->locale('es')->monthName` with `$mesNombre = now()->subMonth()->locale('es')->monthName` so the concepto references the correct month name

---

**File**: `mi3/backend/app/Services/Loan/LoanService.php`

**Function**: `procesarDescuentosMensuales()`

**Specific Changes**:
1. **Change month reference**: Replace `$mes = now()->format('Y-m')` with `$mes = now()->subMonth()->format('Y-m')` so the deduction is attributed to the previous month
2. **Change month name**: Replace `$mesNombre = now()->locale('es')->monthName` with `$mesNombre = now()->subMonth()->locale('es')->monthName` so the concepto references the correct month name

---

**File**: `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`

**Function**: `index()`

**Specific Changes**:
1. **Remove `$isCurrentMonth` guard on NominaService fallback**: Change `if ($totalNomina === 0.0 && $isCurrentMonth)` to `if ($totalNomina === 0.0)` so the `NominaService` fallback runs for any month, not just the current one

---

**File**: `mi3/frontend/components/admin/sections/NominaSection.tsx`

**Function**: `useEffect` that calls `onHeaderConfig`

**Specific Changes**:
1. **Stabilize `trailing` JSX with `useMemo`**: Extract the `trailing` JSX into a `useMemo` hook that depends on `[data, activeTab, generatingLink]` so the reference is stable across renders when the underlying data hasn't changed
2. **Add `useMemo` import**: Add `useMemo` to the React imports
3. **Update useEffect dependencies**: The `useEffect` that calls `onHeaderConfig` should depend on the memoized `trailing` instead of `data` directly, breaking the re-render cycle

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write unit tests that mock `now()` to the 1st of a month and verify the `mes` value used in `AjusteSueldo` creation. For Bug 3, call the dashboard endpoint with a historical month and verify nómina is $0. For Bug 4, render `NominaSection` and count re-renders.

**Test Cases**:
1. **R11 Month Attribution Test**: Mock `now()` to May 1st, run `autoDeduct()`, assert `AjusteSueldo.mes` is `'2026-05'` (will fail — demonstrates bug on unfixed code, should be `'2026-04'`)
2. **Loan Month Attribution Test**: Mock `now()` to May 1st, run `procesarDescuentosMensuales()`, assert `AjusteSueldo.mes` is `'2026-05'` (will fail — demonstrates bug)
3. **EdR Historical Nomina Test**: Request EdR for April (when current month is May) with empty `pagos_nomina`, assert `nomina_mes > 0` from NominaService (will fail — returns $0 on unfixed code)
4. **NominaSection Re-render Test**: Mount `NominaSection` with mock data, count `onHeaderConfig` calls after initial render (will show excessive calls on unfixed code)

**Expected Counterexamples**:
- `AjusteSueldo` records created with `mes = '2026-05-01'` instead of `'2026-04-01'`
- EdR returns `nomina_mes = 0` for historical months despite active workers with shifts
- `onHeaderConfig` called on every render cycle due to unstable `trailing` reference

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  IF input.context = 'cron_r11' OR input.context = 'cron_loan' THEN
    result := runCronOnFirstOfMonth(input)
    ASSERT result.ajusteSueldo.mes = previousMonth(input.cronRunDate)
    ASSERT result.ajusteSueldo.concepto CONTAINS previousMonthName(input.cronRunDate)

  ELSE IF input.context = 'edr_nomina' THEN
    result := getDashboard(input.requestedMonth)
    ASSERT result.nomina_mes = NominaService.getResumen(input.requestedMonth).total

  ELSE IF input.context = 'react_header' THEN
    renderCount := mountAndCountRenders(NominaSection, input.data)
    ASSERT renderCount <= 3  // initial + data load + stabilize
  END IF
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT originalFunction(input) = fixedFunction(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for non-bug inputs (e.g., debtor selection logic, loan status transitions, EdR ventas/CMV calculations), then write property-based tests capturing that behavior.

**Test Cases**:
1. **Debtor Selection Preservation**: Verify that `autoDeduct()` still selects the same set of debtors (users with `es_credito_r11=1`, `credito_r11_usado > 0`, active personal) — only the `mes` field changes
2. **Loan Selection Preservation**: Verify that `procesarDescuentosMensuales()` still selects the same loans (approved, cuotas_pagadas < cuotas, fecha_inicio_descuento <= startOfMonth) — only the `mes` field changes
3. **EdR Ventas Preservation**: Verify that ventas, CMV, OPEX, and meta calculations remain identical for both current and historical months
4. **Approval Flow Preservation**: Verify that `aprobar()` still creates the adjustment with the current month (this is an immediate action, not a cron)
5. **Header Config Preservation**: Verify that tabs, activeTab, onTabChange, and accent values in the header config remain identical after the trailing stabilization

### Unit Tests

- Test `autoDeduct()` with mocked `now()` on day 1 → assert `mes` is previous month
- Test `procesarDescuentosMensuales()` with mocked `now()` on day 1 → assert `mes` is previous month
- Test `DashboardController::index()` with `?month=2026-04` and empty `pagos_nomina` → assert `nomina_mes > 0`
- Test `NominaSection` render count with mock `onHeaderConfig` → assert no infinite loop

### Property-Based Tests

- Generate random dates on the 1st of any month, run `autoDeduct()`, verify `mes` is always the previous month
- Generate random month parameters for EdR, verify nómina fallback activates when `pagos_nomina` is empty regardless of current vs historical
- Generate random payroll data configurations, verify `NominaSection` `onHeaderConfig` stabilizes within 3 render cycles

### Integration Tests

- Full cron execution flow: scheduler triggers `mi3:r11-auto-deduct` → verify `AjusteSueldo`, `R11CreditTransaction`, and credit reset all reference the correct month
- Full EdR flow: navigate to historical month → verify all P&L lines including nómina are populated
- Full admin dashboard flow: load dashboard → navigate to Nómina section → verify no console errors and stable render count
