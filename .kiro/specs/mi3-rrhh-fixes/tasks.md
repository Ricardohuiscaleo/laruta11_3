# Implementation Plan

- [ ] 1. Write bug condition exploration tests
  - **Property 1: Bug Condition** - Rotate Foto 500 + Night Shift Date + Nómina Shape Mismatch
  - **CRITICAL**: These tests MUST FAIL on unfixed code - failure confirms the bugs exist
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **NOTE**: These tests encode the expected behavior - they will validate the fixes when they pass after implementation
  - **GOAL**: Surface counterexamples that demonstrate each bug exists
  - **Bug 1 (Rotate Foto)**: Verify that `PersonalController::rotateFoto()` resolves `Request` type hint correctly and returns HTTP 200 with `{success: true}` — already fixed (import staged), so this should PASS immediately
  - **Bug 2 (Night Shift)**: Test that `DetectAbsencesCommand::handle()` uses D-1 date when running at midnight on D. Create a night shift turno with `fecha=D-1, hora_inicio=18:00`. Run command on D. Assert the absence is detected and ajuste concepto contains D-1 date. On UNFIXED code this FAILS because command queries `fecha=D` and misses the D-1 turno
  - **Bug 3 (Nómina Shape)**: Test that `PayrollController::index()` response contains keys `resumen` (array of WorkerPayroll) and `centros` (array of PayrollSummary). On UNFIXED code this FAILS because response has keys `ruta11`/`seguridad` instead
  - **Scoped PBT Approach**: For Bug 2, scope to concrete case: night shift with hora_inicio=18:00 on D-1. For Bug 3, scope to any valid month string
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Bug 1 test PASSES (already fixed). Bug 2 and Bug 3 tests FAIL (confirms bugs exist)
  - Document counterexamples found to understand root cause
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ] 2. Write preservation property tests (BEFORE implementing fixes)
  - **Property 2: Preservation** - Existing RRHH Behavior Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe: Other PersonalController endpoints (index, store, update, toggle) return correct responses on unfixed code
  - Observe: Daytime shift absence detection works correctly on unfixed code (cajero shift with fecha=D, hora_inicio=08:00 is detected)
  - Observe: `NominaService::getResumen()` returns correct liquidation values (sueldo_base, dias_trabajados, ajustes, totals) on unfixed code
  - Write property-based tests:
    - For all requests to PersonalController endpoints other than rotate-foto, behavior is unchanged
    - For all daytime shifts (hora_inicio < 18:00), absence detection produces same results
    - For all (personal, mes) combinations, liquidation numeric values in transformed response match NominaService output
  - Verify tests PASS on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 3. Bug 1 Fix: Commit + deploy the rotate-foto fix

  - [x] 3.1 Commit and deploy the rotate-foto import fix
    - The `use Illuminate\Http\Request;` import is already staged in `mi3/backend/app/Http/Controllers/Admin/PersonalController.php`
    - Commit with message: "fix: add missing Request import to PersonalController (rotate-foto 500)"
    - Deploy to production
    - _Bug_Condition: isBugCondition_RotateFoto(X) where PersonalController missing Request import_
    - _Expected_Behavior: PATCH /rotate-foto returns HTTP 200 with {success: true}_
    - _Preservation: Other PersonalController endpoints unchanged_
    - _Requirements: 1.1, 2.1, 3.1_

- [x] 4. Bug 2 Fix: Fix DetectAbsencesCommand to use D-1 date for night shift detection

  - [x] 4.1 Change fecha to D-1 in DetectAbsencesCommand::handle()
    - In `mi3/backend/app/Console/Commands/DetectAbsencesCommand.php`, method `handle()`
    - Replace `$fecha = now()->format('Y-m-d')` with `$fecha = now()->subDay()->format('Y-m-d')`
    - This makes the command (running at midnight of D) evaluate shifts from D-1, which have already ended
    - Night shifts with `fecha=D-1` and `hora_inicio >= 18:00` will now be found by `AttendanceService::detectarAusencias()`
    - Update info message to reflect: "Detectando inasistencias para {$fecha} (ayer)..."
    - _Bug_Condition: isBugCondition_NightShift(X) where turno.fecha = D-1 AND command queries fecha=D_
    - _Expected_Behavior: detectarAusencias(D-1) finds night shifts and creates ajuste with concepto "Inasistencia D-1"_
    - _Preservation: Daytime shifts still detected correctly (they also have fecha=D-1 when command runs on D)_
    - _Requirements: 1.2, 1.3, 2.2, 2.3, 3.2, 3.3_

  - [x] 4.2 Verify bug condition exploration test now passes for Bug 2
    - **Property 1: Expected Behavior** - Night Shift Absence Uses D-1
    - **IMPORTANT**: Re-run the SAME test from task 1 (Bug 2 portion) - do NOT write a new test
    - The test from task 1 encodes the expected behavior: command detects D-1 night shifts
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.2, 2.3_

  - [x] 4.3 Verify preservation tests still pass for Bug 2
    - **Property 2: Preservation** - Daytime Shift Detection Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 (daytime shift portion) - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm daytime shift absence detection still works correctly

- [x] 5. Bug 3 Fix: Transform PayrollController::index() response to match frontend expected shape

  - [x] 5.1 Add data transformation in PayrollController::index()
    - In `mi3/backend/app/Http/Controllers/Admin/PayrollController.php`, method `index()`
    - After `$resumen = $this->nominaService->getResumen($mes)`, add transformation logic:
    - Build `resumen` array: iterate over `$resumen['ruta11']['personal']` and `$resumen['seguridad']['personal']`, flatten each entry to WorkerPayroll shape: `{personal_id, nombre, rol, sueldo_base, dias_trabajados, reemplazos, ajustes_total, gran_total}`
    - Deduplicate workers appearing in both centers: merge their values (sum sueldo_base, dias_trabajados, reemplazos, ajustes_total, gran_total from both liquidaciones)
    - Build `centros` array: for each center (ruta11, seguridad), create PayrollSummary: `{centro_costo, presupuesto, total_sueldos (sum of sueldo_base), total_pagado (sum of pagos->monto), diferencia (presupuesto - total_sueldos)}`
    - Return `response()->json(['success' => true, 'data' => ['resumen' => $workerPayrolls, 'centros' => $centrosSummary]])`
    - Do NOT modify `NominaService::getResumen()` — transformation happens only in the controller
    - _Bug_Condition: isBugCondition_Nomina(X) where response has ruta11/seguridad but not resumen/centros_
    - _Expected_Behavior: response.data.resumen is Array<WorkerPayroll>, response.data.centros is Array<PayrollSummary>_
    - _Preservation: NominaService calculations unchanged, only response shape transforms_
    - _Requirements: 1.4, 1.5, 2.4, 2.5, 3.4, 3.5_

  - [x] 5.2 Verify bug condition exploration test now passes for Bug 3
    - **Property 1: Expected Behavior** - Nómina Returns Expected Shape
    - **IMPORTANT**: Re-run the SAME test from task 1 (Bug 3 portion) - do NOT write a new test
    - The test from task 1 encodes the expected behavior: response has resumen + centros keys
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.4, 2.5_

  - [x] 5.3 Verify preservation tests still pass for Bug 3
    - **Property 2: Preservation** - Payroll Calculations Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 (payroll values portion) - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions in liquidation values)
    - Confirm all numeric values match NominaService output

- [x] 6. Commit + push + deploy all fixes
  - Commit Bug 2 and Bug 3 changes together (Bug 1 already committed in task 3)
  - Commit message: "fix: night shift D-1 date + nómina response shape transform"
  - Push to remote
  - Deploy to production
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

- [x] 7. Checkpoint - Verify all 3 fixes work in production
  - Verify Bug 1: PATCH `/api/v1/admin/personal/{id}/rotate-foto` returns 200 with `{success: true}`
  - Verify Bug 2: Run `mi3:detect-absences` and confirm night shift absences are detected with correct D-1 date
  - Verify Bug 3: Open `/admin/nomina` and confirm worker cards and cost center summaries render correctly
  - Ensure all tests pass, ask the user if questions arise
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5_
