# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Cron Deductions Use Current Month Instead of Previous
  - **CRITICAL**: This test MUST FAIL on unfixed code — failure confirms the bugs exist
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior — it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the month attribution bug
  - **Scoped PBT Approach**: Scope to concrete failing cases — cron execution on the 1st of any month
  - Bug 1: In `R11CreditService::autoDeduct()`, mock `now()` to May 1st, run autoDeduct, assert `AjusteSueldo.mes` equals `'2026-04-01'` and concepto contains "abril" (will fail — code uses `now()` which gives May)
  - Bug 2: In `LoanService::procesarDescuentosMensuales()`, mock `now()` to May 1st, run procesarDescuentosMensuales, assert `AjusteSueldo.mes` equals `'2026-04-01'` and concepto contains "abril" (will fail — same `now()` bug)
  - Bug 3: Call `DashboardController::index()` with `?month=2026-04` (historical month) and empty `pagos_nomina`/`compras`, assert `nomina_mes > 0` from NominaService fallback (will fail — `$isCurrentMonth` guard blocks it)
  - Bug 4: Mount `NominaSection` with mock data and count `onHeaderConfig` calls — assert stabilizes within 3 renders (will fail — inline `trailing` JSX causes infinite loop)
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests FAIL (this is correct — it proves the bugs exist)
  - Document counterexamples found to understand root cause
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Existing Business Logic Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe: `autoDeduct()` debtor selection logic (users with `es_credito_r11=1`, `credito_r11_usado > 0`, active personal) — verify selection is correct on unfixed code
  - Observe: `autoDeduct()` creates `R11CreditTransaction` refund and resets `credito_r11_usado` to 0
  - Observe: `procesarDescuentosMensuales()` selects approved loans with `cuotas_pagadas < cuotas` and `fecha_inicio_descuento <= startOfMonth` — verify selection is correct
  - Observe: `procesarDescuentosMensuales()` marks loans as `pagado` after deduction
  - Observe: `aprobar()` creates adjustment with current month `mes` (immediate action, not cron — must remain unchanged)
  - Observe: `DashboardController::index()` ventas, CMV, OPEX calculations remain identical
  - Observe: When `pagos_nomina` records exist, they are used as primary nomina source
  - Write tests capturing these observed behaviors
  - Verify tests pass on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8_

- [x] 3. Fix nomina & EdR bugs

  - [x] 3.1 Fix R11CreditService month attribution
    - In `mi3/backend/app/Services/Credit/R11CreditService.php`, method `autoDeduct()`
    - Change `$mes = now()->format('Y-m')` to `$mes = now()->subMonth()->format('Y-m')`
    - Change `$mesNombre = now()->locale('es')->monthName` to `$mesNombre = now()->subMonth()->locale('es')->monthName`
    - _Bug_Condition: isBugCondition(input) where input.context = 'cron_r11' AND autoDeduct() uses now() instead of now()->subMonth()_
    - _Expected_Behavior: AjusteSueldo.mes = M-1 AND concepto contains previous month name_
    - _Preservation: Debtor selection, R11CreditTransaction creation, credit reset logic unchanged_
    - _Requirements: 2.1, 2.2, 3.1_

  - [x] 3.2 Fix LoanService month attribution
    - In `mi3/backend/app/Services/Loan/LoanService.php`, method `procesarDescuentosMensuales()`
    - Change `$mes = now()->format('Y-m')` to `$mes = now()->subMonth()->format('Y-m')`
    - Change `$mesNombre = now()->locale('es')->monthName` to `$mesNombre = now()->subMonth()->locale('es')->monthName`
    - _Bug_Condition: isBugCondition(input) where input.context = 'cron_loan' AND procesarDescuentosMensuales() uses now() instead of now()->subMonth()_
    - _Expected_Behavior: AjusteSueldo.mes = M-1 AND concepto contains previous month name_
    - _Preservation: Loan selection, pagado marking, approval flow unchanged_
    - _Requirements: 2.3, 2.4, 3.2, 3.3_

  - [x] 3.3 Fix DashboardController NominaService fallback guard
    - In `mi3/backend/app/Http/Controllers/Admin/DashboardController.php`, method `index()`
    - Change `if ($totalNomina === 0.0 && $isCurrentMonth)` to `if ($totalNomina === 0.0)`
    - This removes the `$isCurrentMonth` restriction so NominaService fallback works for any month
    - _Bug_Condition: isBugCondition(input) where input.context = 'edr_nomina' AND $isCurrentMonth guard blocks fallback for historical months_
    - _Expected_Behavior: NominaService::getResumen() runs as fallback for any month with empty pagos_nomina_
    - _Preservation: pagos_nomina remains primary source; ventas, CMV, OPEX calculations unchanged_
    - _Requirements: 2.5, 2.6, 3.4, 3.5_

  - [x] 3.4 Fix NominaSection trailing JSX stability
    - In `mi3/frontend/components/admin/sections/NominaSection.tsx`
    - Add `useMemo` to the import: `import { useEffect, useState, useCallback, useMemo } from 'react'`
    - Extract the `trailing` JSX into a `useMemo` hook with dependencies `[data, activeTab, generatingLink]`
    - Update the `useEffect` that calls `onHeaderConfig` to use the memoized `trailing` reference
    - _Bug_Condition: isBugCondition(input) where input.context = 'react_header' AND trailing JSX is recreated inline on every render_
    - _Expected_Behavior: trailing reference is stable across renders when data hasn't changed, preventing infinite re-render loop_
    - _Preservation: Header config tabs, activeTab, onTabChange, accent values unchanged_
    - _Requirements: 2.7, 3.6_

  - [x] 3.5 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Cron Deductions Use Previous Month
    - **IMPORTANT**: Re-run the SAME test from task 1 — do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bugs are fixed)
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

  - [x] 3.6 Verify preservation tests still pass
    - **Property 2: Preservation** - Existing Business Logic Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 — do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)

- [x] 4. Checkpoint - Ensure all tests pass
  - Run full test suite to confirm all exploration and preservation tests pass
  - Verify no React console errors in NominaSection
  - Ensure all tests pass, ask the user if questions arise
