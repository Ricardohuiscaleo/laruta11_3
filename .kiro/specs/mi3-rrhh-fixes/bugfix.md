# Bugfix Requirements Document

## Introduction

This document covers three bugs in the mi3 (La Ruta 11 Work) application affecting the RRHH/admin module: a 500 error on the rotate-foto endpoint, incorrect date attribution for night-shift absences, and an empty nómina page due to a frontend/backend data shape mismatch.

## Bug Analysis

### Bug 1: Rotate Foto 500 Error

#### Current Behavior (Defect)

1.1 WHEN an admin sends PATCH `/api/v1/admin/personal/{id}/rotate-foto` THEN the system returns HTTP 500 because the `Illuminate\Http\Request` import was missing in `PersonalController.php`

#### Expected Behavior (Correct)

2.1 WHEN an admin sends PATCH `/api/v1/admin/personal/{id}/rotate-foto` with a `rotation` value THEN the system SHALL update the `foto_rotation` field on the personal record and return `{"success": true}` with HTTP 200

#### Unchanged Behavior (Regression Prevention)

3.1 WHEN an admin calls any other PersonalController endpoint (index, store, update, toggle) THEN the system SHALL CONTINUE TO function correctly with no changes in behavior

---

### Bug 2: Inasistencias Fecha Incorrecta para Turnos Nocturnos

#### Current Behavior (Defect)

1.2 WHEN `DetectAbsencesCommand` runs at midnight (00:00) on date D and a planchero had a night shift starting on date D-1 at 18:00 ending on date D at 01:00 THEN the system queries `Turno::whereDate('fecha', D)` and creates an ajuste with concepto "Inasistencia D" instead of "Inasistencia D-1"

1.3 WHEN `DetectAbsencesCommand` runs at midnight on date D THEN the system fails to find the planchero's shift for D-1 (which has `fecha = D-1`) and either misses the absence entirely or attributes it to the wrong date

#### Expected Behavior (Correct)

2.2 WHEN `DetectAbsencesCommand` runs at midnight on date D and a planchero had a night shift starting on D-1 THEN the system SHALL detect the shift by querying for `fecha = D-1` (the shift's start date) and create the ajuste with concepto "Inasistencia D-1" referencing the actual shift date

2.3 WHEN `DetectAbsencesCommand` runs at midnight on date D THEN the system SHALL check attendance for the previous day's shifts (D-1) so that night shifts that cross midnight are correctly evaluated after they end

#### Unchanged Behavior (Regression Prevention)

3.2 WHEN `DetectAbsencesCommand` detects absences for daytime shifts (e.g., cajero shifts that start and end on the same calendar day) THEN the system SHALL CONTINUE TO correctly detect the absence and create the ajuste with the correct shift date

3.3 WHEN a worker has completed their checklist (presencial or virtual) for a shift THEN the system SHALL CONTINUE TO recognize them as present and not create an absence ajuste

---

### Bug 3: Nómina Page Shows Empty

#### Current Behavior (Defect)

1.4 WHEN the admin navigates to the Nómina page THEN the frontend expects `data.resumen` (array of workers) and `data.centros` (array of cost center summaries) but the backend `NominaService::getResumen()` returns `data.ruta11` and `data.seguridad` (grouped by cost center with nested `personal`, `pagos`, `presupuesto`), causing the page to render empty

1.5 WHEN the frontend reads `data?.resumen?.map(...)` and `data?.centros` THEN both are `undefined` because the backend response shape does not contain these keys

#### Expected Behavior (Correct)

2.4 WHEN the admin navigates to the Nómina page THEN the system SHALL display worker payroll cards with nombre, rol, sueldo_base, dias_trabajados, reemplazos, ajustes_total, and gran_total for each active worker

2.5 WHEN the admin navigates to the Nómina page THEN the system SHALL display cost center summary cards (La Ruta 11 and Seguridad) showing presupuesto, total_sueldos, total_pagado, and diferencia

#### Unchanged Behavior (Regression Prevention)

3.4 WHEN the backend `NominaService::getResumen()` calculates liquidaciones for each worker THEN the system SHALL CONTINUE TO correctly compute sueldo_base, dias_trabajados, reemplazos, ajustes, and totals per cost center

3.5 WHEN the admin uses month navigation (prev/next) on the Nómina page THEN the system SHALL CONTINUE TO fetch and display the correct month's payroll data

---

## Bug Condition Derivation

### Bug 1: Rotate Foto

```pascal
FUNCTION isBugCondition_RotateFoto(X)
  INPUT: X of type HTTPRequest
  OUTPUT: boolean

  RETURN X.method = "PATCH" AND X.path MATCHES "/api/v1/admin/personal/{id}/rotate-foto"
         AND PersonalController is missing "use Illuminate\Http\Request" import
END FUNCTION

// Property: Fix Checking
FOR ALL X WHERE isBugCondition_RotateFoto(X) DO
  result ← rotateFoto'(X)
  ASSERT result.status = 200 AND result.body.success = true
END FOR

// Property: Preservation Checking
FOR ALL X WHERE NOT isBugCondition_RotateFoto(X) DO
  ASSERT F(X) = F'(X)
END FOR
```

### Bug 2: Night Shift Date

```pascal
FUNCTION isBugCondition_NightShift(X)
  INPUT: X of type DetectionContext { detectionDate: Date, turno: Turno }
  OUTPUT: boolean

  RETURN X.turno.horaInicio >= 18:00
         AND X.turno.horaFin <= 06:00 (next day)
         AND X.detectionDate = X.turno.fecha + 1 day
END FUNCTION

// Property: Fix Checking — night shifts get correct date
FOR ALL X WHERE isBugCondition_NightShift(X) DO
  result ← detectarAusencias'(X.detectionDate)
  ajuste ← findAjusteForPersonal(X.turno.personal_id)
  ASSERT ajuste.concepto CONTAINS X.turno.fecha (shift start date, not detection date)
END FOR

// Property: Preservation Checking — daytime shifts unchanged
FOR ALL X WHERE NOT isBugCondition_NightShift(X) DO
  ASSERT detectarAusencias(X) = detectarAusencias'(X)
END FOR
```

### Bug 3: Nómina Data Shape

```pascal
FUNCTION isBugCondition_Nomina(X)
  INPUT: X of type PayrollAPIResponse
  OUTPUT: boolean

  RETURN X.data HAS KEYS ("ruta11", "seguridad")
         AND X.data DOES NOT HAVE KEYS ("resumen", "centros")
END FUNCTION

// Property: Fix Checking — frontend receives expected shape
FOR ALL X WHERE isBugCondition_Nomina(X) DO
  response ← getPayroll'(X.mes)
  ASSERT response.data HAS KEY "resumen" AND typeof(response.data.resumen) = Array<WorkerPayroll>
  ASSERT response.data HAS KEY "centros" AND typeof(response.data.centros) = Array<PayrollSummary>
END FOR

// Property: Preservation Checking — underlying calculations unchanged
FOR ALL X WHERE NOT isBugCondition_Nomina(X) DO
  ASSERT liquidacionValues(F(X)) = liquidacionValues(F'(X))
END FOR
```
